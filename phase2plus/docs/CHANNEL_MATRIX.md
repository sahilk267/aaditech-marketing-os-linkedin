# Official publisher capability matrix

| Channel | Source adapter | Default operation | Required later |
|---|---|---|---|
| LinkedIn organization text | Official `POST /rest/posts` adapter | `manual_ready` until OAuth + admin approval | LinkedIn marketing product app approval, `w_organization_social`, admin/content-admin page role, tested supported API version |
| LinkedIn image/video/document | Not implemented | `manual_ready` | Official media upload/create flow, permissions, validation |
| Facebook Pages | Manual-only adapter | `manual_ready` | Official Page API permissions/app review |
| Instagram Professional | Manual-only adapter | `manual_ready` | Professional account, authorized content publish API |
| YouTube | Manual-only adapter | `manual_ready` | Data API upload scope/quota/audit |
| Pinterest | Manual-only adapter | `manual_ready` | Official pin creation scope/format validation |
| Threads | Manual-only adapter | `manual_ready` | Official Threads API grant and testing |
| TikTok | Manual-only adapter | `manual_ready` | TikTok Content Posting API access & audit |
| X | Excluded | No automatic adapter | Verify *free* official capability without extra spend |

Never automate a browser, use session cookies, scraping behind login, fake engagement or unofficial endpoints. LinkedIn text only uses the official Posts API and requires `Linkedin-Version` and `X-Restli-Protocol-Version` headers. Default `api_access_approved=false` even after OAuth.
