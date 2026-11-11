"""CPU-only editable SVG creative, original brand-safe template."""
from __future__ import annotations
import base64,html,re,subprocess
from pathlib import Path


def safe_color(value:str,fallback:str)->str:
    return value if isinstance(value,str) and re.fullmatch(r'#[0-9A-Fa-f]{6}',value) else fallback

def render_svg(brand_name:str,title:str,body:str,primary='#17324D',accent='#20C997')->str:
    primary=safe_color(primary,'#17324D');accent=safe_color(accent,'#20C997')
    brand=html.escape(brand_name[:56],quote=True)
    words=' '.join(str(title or body).split()).split(' ')
    lines=[];line=''
    for word in words:
        if len(line+' '+word)>39:
            if line:lines.append(line);line=''
        line=(line+' '+word).strip()
        if len(lines)>=4:break
    if line and len(lines)<4:lines.append(line)
    text=''.join(f'<text x="85" y="{225+i*76}" fill="#FFFFFF" font-size="55" font-weight="bold" font-family="Arial, sans-serif">{html.escape(line[:70])}</text>' for i,line in enumerate(lines))
    return ('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="627" viewBox="0 0 1200 627">'
        f'<rect width="1200" height="627" fill="{primary}"/>'
        f'<rect x="0" y="0" width="16" height="627" fill="{accent}"/>'
        f'<text x="85" y="90" fill="{accent}" font-size="33" font-weight="bold" font-family="Arial, sans-serif">{brand}</text>'
        +text+'<text x="85" y="565" fill="#FFFFFF" font-size="24" font-family="Arial, sans-serif">Ideas worth sharing</text></svg>')

def media_result(brand_name,title,body,primary='#17324D',accent='#20C997'):
    svg=render_svg(brand_name,title,body,primary,accent)
    return {'svg_base64':base64.b64encode(svg.encode()).decode('ascii')}

def ffmpeg_video_from_owned_image(image:Path,out:Path,duration:float=5.0):
    """Optional local video: image must be owned/licensed. Never run model supplied shell commands."""
    if duration<1 or duration>60 or image.suffix.lower() not in ('.png','.jpg','.jpeg'):
        raise ValueError('Invalid media parameters')
    if not image.is_file():raise FileNotFoundError(image)
    out.parent.mkdir(parents=True,exist_ok=True)
    command=['ffmpeg','-y','-loop','1','-i',str(image),'-t',str(duration),
        '-vf','scale=1280:720:force_original_aspect_ratio=decrease,pad=1280:720:(ow-iw)/2:(oh-ih)/2',
        '-r','25','-c:v','libx264','-pix_fmt','yuv420p',str(out)]
    subprocess.run(command,check=True,timeout=180,capture_output=True)

def _png_card(brand:str, title:str, body:str, primary:str,accent:str, size:tuple[int,int]=(1200,627))->bytes:
    """Native CPU renderer; no browser, paid API, or external image ownership needed."""
    from io import BytesIO
    from PIL import Image, ImageDraw, ImageFont
    w,h=size
    im=Image.new('RGB',size,safe_color(primary,'#17324D'))
    d=ImageDraw.Draw(im)
    d.rectangle((0,0,18,h),fill=safe_color(accent,'#20C997'))
    def font(sz):
        try:return ImageFont.truetype('DejaVuSans.ttf',sz)
        except OSError:return ImageFont.load_default()
    d.text((70,54),str(brand)[:50],font=font(30),fill=safe_color(accent,'#20C997'))
    text=' '.join(str(title or body).split())
    limit=29 if w==1080 else 41
    words=text.split();lines=[];current=''
    for word in words:
        if len(current+' '+word)>limit:
            if current:lines.append(current)
            current=word
        else:current=(current+' '+word).strip()
        if len(lines)>=4:break
    if current and len(lines)<4:lines.append(current)
    for n,line in enumerate(lines):
        d.text((70,190+n*80),line[:limit],font=font(48),fill='#FFFFFF')
    d.text((70,h-74),'Aadi Marketing OS | Original creative',font=font(18),fill='#FFFFFF')
    out=BytesIO();im.save(out,'PNG',optimize=True);return out.getvalue()


def media_result(brand_name,title,body,primary='#17324D',accent='#20C997'):
    # Keep SVG for editability, alongside pre-rendered production-sized PNGs.
    svg=render_svg(brand_name,title,body,primary,accent)
    slides=[
        _png_card(brand_name,title,body,primary,accent,(1080,1080)),
        _png_card(brand_name,'The key idea',body[:240],primary,accent,(1080,1080)),
        _png_card(brand_name,'What to do next','Review the source and apply this idea.',primary,accent,(1080,1080)),
    ]
    return {
        'svg_base64':base64.b64encode(svg.encode()).decode('ascii'),
        'png_base64':base64.b64encode(_png_card(brand_name,title,body,primary,accent)).decode('ascii'),
        'carousel_png_base64':[base64.b64encode(x).decode('ascii') for x in slides],
    }
