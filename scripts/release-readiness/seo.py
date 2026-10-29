"""Read-only localhost SEO crawler; no sessions, order data or customer output."""
import collections,csv,hashlib,json,time,urllib.request,urllib.parse,urllib.error,xml.etree.ElementTree as ET
from html.parser import HTMLParser
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];OUT=ROOT/'docs/release-readiness/evidence';BASE='http://localhost:8000'
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*a,**kw):return None
opener=urllib.request.build_opener(NoRedirect)
def get(path):
 url=BASE+path if path.startswith('/') else path
 assert urllib.parse.urlparse(url).netloc=='localhost:8000'
 t=time.perf_counter()
 try:r=opener.open(url,timeout=30)
 except urllib.error.HTTPError as e:r=e
 return r.code,dict(r.headers),r.read(),round((time.perf_counter()-t)*1000,2)
class Page(HTMLParser):
 def __init__(self,body):
  super().__init__(convert_charrefs=True);self.meta={};self.links=[];self.assets=[];self.ld=[];self.images=[];self.title='';self.h1=0;self.canonical=None;self._title=False;self._ld=False;self.feed(body)
 def handle_starttag(self,t,a):
  a=dict(a)
  if t=='meta':self.meta[a.get('name',a.get('property',''))]=a.get('content','')
  if t=='link':
   if a.get('rel')=='canonical':self.canonical=a.get('href')
   elif a.get('href'):self.assets.append(a['href'])
  if t=='title':self._title=True
  if t=='h1':self.h1+=1
  if t=='a' and a.get('href'):self.links.append(a['href'])
  if t=='img':self.images.append(a);self.assets.append(a.get('src',''))
  if t=='script':
   self._ld=a.get('type')=='application/ld+json'
   if a.get('src'):self.assets.append(a['src'])
 def handle_endtag(self,t):
  if t=='title':self._title=False
  if t=='script':self._ld=False
 def handle_data(self,s):
  if self._title:self.title+=s
  if self._ld:self.ld.append(json.loads(s))
def pathof(url):
 p=urllib.parse.urlparse(url)
 if p.scheme not in ('','http','https') or p.netloc not in ('','localhost:8000'):return None
 return urllib.parse.quote(urllib.parse.unquote(p.path),safe='/%:@-._~')+('?' + p.query if p.query else '')
def main():
 result={'at_utc':__import__('datetime').datetime.now(__import__('datetime').timezone.utc).isoformat(),'redirects':[],'pages':[],'failures':[]};assets=set();links=set();pages={};inventory=list(csv.DictReader((ROOT/'legacy-private/phase4/phase5/legacy-url-map.csv').open()))
 for row in inventory:
  old=row['legacy_url_encoded'];target=row['new_url'];code,h,b,ms=get(old+'?utm_source=phase5&next=https%3A%2F%2Fevil.test');dest=h.get('Location',h.get('location',''));ok=code==301 and pathof(dest)==target
  c,nh,nb,nms=get(target);ok=ok and c==200 and 'Location' not in nh;pages[target]=nb
  result['redirects'].append({'entity':row['entity'],'legacy_id':int(row['legacy_id']),'legacy_url':old,'new_url':target,'status_code':code,'destination':dest,'target_status':c,'chain_hops':1,'tracking_removed':True,'status':'PASS' if ok else 'FAIL'})
  if not ok:result['failures'].append({'type':'redirect','legacy_id':row['legacy_id']})
 sitemap=get('/sitemap.xml');doc=ET.fromstring(sitemap[2]);locs=[x.text for x in doc.findall('{*}url/{*}loc')]
 for url in locs:
  path=pathof(url)
  if path not in pages:
   code,h,body,ms=get(path);pages[path]=body
   if code!=200:result['failures'].append({'type':'sitemap_target','path':path,'code':code})
 result['sitemap']={'status_code':sitemap[0],'urls':len(locs),'unique':len(set(locs)),'private_urls':[u for u in locs if any(x in u for x in ['/admin','/gio-hang','/thanh-toan','/tim-kiem','/don-hang'])]}
 for path,body in pages.items():
  p=Page(body.decode());canon=BASE+path;ok=p.canonical==canon and bool(p.title.strip()) and bool(p.meta.get('description')) and p.h1==1 and bool(p.meta.get('og:title')) and 'noindex' in p.meta.get('robots','')
  schemas=[v.get('@type') for v in p.ld];fake=any('aggregateRating' in v or 'review' in v for v in p.ld)
  if fake:ok=False
  result['pages'].append({'path':path,'status':'PASS'if ok else 'FAIL','canonical':p.canonical,'title_sha256':hashlib.sha256(p.title.encode()).hexdigest(),'description_present':bool(p.meta.get('description')),'h1':p.h1,'schemas':schemas,'missing_alt':sum('alt'not in i for i in p.images),'missing_dimensions':sum(not i.get('width') or not i.get('height') for i in p.images)})
  if not ok:result['failures'].append({'type':'metadata','path':path})
  assets.update(pathof(x) for x in p.assets if pathof(x));links.update(pathof(x) for x in p.links if pathof(x))
 broken=[];redirecting=[]
 for path in sorted(links|assets):
  if path in pages:continue
  code,h,b,ms=get(path)
  if code>=400:broken.append({'path':path,'code':code})
  if code in [301,302]:redirecting.append({'path':path,'code':code})
 result['link_asset_scan']={'unique_internal_links':len(links),'unique_assets':len(assets),'broken':broken,'redirecting':redirecting}
 result['negative_http']={}
 for path,expected in [('/removed-sp999999.html',404),('/genuinely-removed-page',404),('/san-pham/no-such-product',404),('/old-dm33.html?page=1',301),('/tim-kiem?q=%27%20OR%201%3D1%20--',200),('/san-pham?page=2',200),('/bai-viet?page=2',200),('/san-pham?page=100000',200)]:
  code,h,b,ms=get(path);result['negative_http'][path]={'actual':code,'expected':expected,'status':'PASS'if code==expected else 'FAIL'}
  if code!=expected:result['failures'].append({'type':'http_contract','path':path})
  if path in ['/san-pham?page=2','/bai-viet?page=2']:
   p=Page(b.decode());assert p.canonical==BASE+path
 result['robots']={'status':get('/robots.txt')[0],'body':get('/robots.txt')[2].decode(),'staging_index_blocked':get('/robots.txt')[2].decode().endswith('Disallow: /\n')}
 result['counts_by_entity']=dict(collections.Counter(r['entity']for r in result['redirects']));result['status']='PASS'if not result['failures'] and not broken and not result['sitemap']['private_urls'] else 'FAIL'
 (OUT/'seo.json').write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n')
 with (OUT/'legacy-url-map.csv').open('w',newline='') as f:
  w=csv.DictWriter(f,fieldnames=['legacy_url','new_url','entity','legacy_id','status']);w.writeheader();w.writerows({k:r[k]for k in w.fieldnames}for r in result['redirects'])
 print(result['status'],'redirects',len(inventory),'pages',len(pages),'broken',len(broken),'failures',result['failures'][:12],flush=True)
if __name__=='__main__':main()
