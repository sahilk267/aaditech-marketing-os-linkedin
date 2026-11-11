"""Public-HTTPS fetcher with DNS pinning, robots exclusion and no login bypasses."""
from __future__ import annotations
from html.parser import HTMLParser
from urllib.parse import urlparse,urljoin
from urllib import robotparser
import http.client,ipaddress,re,socket,ssl,time

AGENT='AadiMarketingOSBot/0.2 (+public-research; contact site owner)'
class UnsafeSource(Exception):pass

class PlainText(HTMLParser):
    def __init__(self):super().__init__(convert_charrefs=True);self.text=[];self.skip=0
    def handle_starttag(self,tag,attrs):
        if tag in ('script','style','noscript'):self.skip+=1
    def handle_endtag(self,tag):
        if tag in ('script','style','noscript') and self.skip:self.skip-=1
    def handle_data(self,text):
        if not self.skip:self.text.append(text)

def validate_url(url:str)->tuple[str,str]:
    if not isinstance(url,str) or len(url)>2048 or re.search(r'\s',url):raise UnsafeSource('INVALID_URL')
    p=urlparse(url)
    if p.scheme!='https' or not p.hostname or p.username or p.password or p.fragment or p.port not in (None,443):raise UnsafeSource('HTTPS_ONLY')
    host=p.hostname.strip('.').lower()
    if '.' not in host or not re.fullmatch(r'[a-z0-9.-]+',host) or host.endswith(('.local','.internal','.test','.invalid','.localhost')):
        raise UnsafeSource('UNSAFE_HOST')
    if host in ('localhost','127.0.0.1'):raise UnsafeSource('PRIVATE_HOST')
    try:ipaddress.ip_address(host);raise UnsafeSource('IP_HOST_DISALLOWED')
    except ValueError:pass
    return host,p.path or '/'

def resolve_public_ip(host:str)->str:
    try:addresses=socket.getaddrinfo(host,443,type=socket.SOCK_STREAM)
    except OSError as exc:raise UnsafeSource('DNS_FAILED') from exc
    if not addresses:raise UnsafeSource('DNS_EMPTY')
    ips=[]
    for entry in addresses:
        ip=ipaddress.ip_address(entry[4][0])
        if not ip.is_global:raise UnsafeSource('PRIVATE_DNS_ANSWER')
        ips.append(str(ip))
    return ips[0]

class PinnedConnection(http.client.HTTPSConnection):
    def __init__(self,hostname:str,ip:str,timeout:int=12):
        super().__init__(hostname,port=443,timeout=timeout,context=ssl.create_default_context());self._ip=ip
    def connect(self):
        sock=socket.create_connection((self._ip,443),self.timeout)
        self.sock=self._context.wrap_socket(sock,server_hostname=self.host)

def single_get(url:str)->tuple[int,bytes,dict]:
    host,path=validate_url(url)
    ip=resolve_public_ip(host)
    p=urlparse(url)
    target=path+('?' + p.query if p.query else '')
    conn=PinnedConnection(host,ip)
    try:
        conn.request('GET',target,headers={'Host':host,'User-Agent':AGENT,'Accept':'text/html,application/rss+xml,application/atom+xml,text/plain'})
        r=conn.getresponse()
        # Hard 256 KiB cap irrespective of server Content-Length.
        data=r.read(256*1024+1)
        if len(data)>256*1024:raise UnsafeSource('RESPONSE_TOO_LARGE')
        headers={k.lower():v for k,v in r.getheaders()}
        return r.status,data,headers
    except (OSError,ssl.SSLError,http.client.HTTPException) as exc:raise UnsafeSource('FETCH_FAILED') from exc
    finally:conn.close()

def fetch_url(url:str,max_redirects:int=3)->tuple[bytes,str]:
    current=url
    for _ in range(max_redirects+1):
        status,data,headers=single_get(current)
        if status in (301,302,303,307,308):
            location=headers.get('location')
            if not location:raise UnsafeSource('REDIRECT_MISSING')
            current=urljoin(current,location)
            validate_url(current)
            continue
        if status!=200:raise UnsafeSource('HTTP_'+str(status))
        mime=headers.get('content-type','').split(';')[0].strip().lower()
        if mime not in ('text/plain','text/html','application/rss+xml','application/atom+xml','application/xml','text/xml'):
            raise UnsafeSource('CONTENT_TYPE_BLOCKED')
        return data,current
    raise UnsafeSource('TOO_MANY_REDIRECTS')

def check_robots(url:str)->None:
    host,_=validate_url(url)
    robots='https://'+host+'/robots.txt'
    try:status,data,headers=single_get(robots)
    except UnsafeSource:raise UnsafeSource('ROBOTS_UNAVAILABLE')
    if status in (401,403):raise UnsafeSource('ROBOTS_DENIED')
    if status in (404,410):return
    if status!=200:raise UnsafeSource('ROBOTS_UNAVAILABLE')
    rules=robotparser.RobotFileParser();rules.parse(data.decode('utf-8','replace').splitlines())
    if not rules.can_fetch(AGENT,url):raise UnsafeSource('ROBOTS_DENIED')
    delay=rules.crawl_delay(AGENT)
    if delay is not None and delay>60:raise UnsafeSource('CRAWL_DELAY_EXCEEDED')
    if delay:time.sleep(float(delay))

def fetch_public_notes(url:str)->dict:
    validate_url(url)
    check_robots(url)
    data,final_url=fetch_url(url)
    parser=PlainText();parser.feed(data.decode('utf-8','replace'))
    text=' '.join(' '.join(parser.text).split())[:12000]
    if not text:raise UnsafeSource('EMPTY_CONTENT')
    title=text[:180]
    # These are unverified source excerpts, not asserted facts. Human verification is required.
    excerpts=[x[:900] for x in re.split(r'(?<=[.!?])\s+',text) if len(x)>40][:6]
    return {'title':title,'summary':text[:2800],
            'facts':[{'text':x,'source_url':final_url,'status':'unverified'} for x in excerpts]}
