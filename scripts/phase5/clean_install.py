"""Fresh source-only local install. Own DB/account; never resets an operational DB."""
from pathlib import Path
import base64,datetime,hashlib,json,os,re,secrets,shutil,subprocess,sys
ROOT=Path(__file__).resolve().parents[2];sys.path.insert(0,str(ROOT/'scripts/readiness'));from verify_backup import query
P=ROOT/'legacy-private/phase5';OUT=ROOT/'docs/phase-5/evidence';stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S');database='bancay_phase5_clean_'+stamp;username='bancay_p5_clean';checkout=P/('clean-'+stamp);checkout.mkdir();os.chmod(checkout,0o700)
paths=subprocess.check_output(['git','ls-files','-z','--cached','--others','--exclude-standard'],cwd=ROOT).decode().split('\0');copied=[]
for f in dict.fromkeys(paths):
 if not f or f.startswith(('legacy-private/','node_modules/','vendor/','storage/','docs/phase-5/evidence/')):continue
 src=ROOT/f
 if src.is_file():
  dst=checkout/f;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(src,dst);copied.append(f)
for f in ['storage/app/public','storage/app/plugins','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache']:(checkout/f).mkdir(parents=True,exist_ok=True)
env=dict(l.split('=',1)for l in (ROOT/'.env.example').read_text().splitlines()if'='in l and not l.startswith('#'));password=secrets.token_hex(24);env.update(APP_KEY='base64:'+base64.b64encode(secrets.token_bytes(32)).decode(),DB_DATABASE=database,DB_USERNAME=username,DB_PASSWORD=password,BANCAY_BOOTSTRAP_PASSWORD=secrets.token_urlsafe(32));(checkout/'.env').write_text('\n'.join(k+'='+v for k,v in env.items())+'\n');os.chmod(checkout/'.env',0o600)
assert database.startswith('bancay_phase5_clean_')and not query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{database}';")
query(f"CREATE DATABASE `{database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '{username}'@'%' IDENTIFIED BY '{password}'; GRANT ALL ON `{database}`.* TO '{username}'@'%';")
result={'at_utc':datetime.datetime.now(datetime.timezone.utc).isoformat(),'source_files':len(copied),'copied_vendor':False,'copied_node_modules':False,'copied_initialized_database':False,'steps':[]}
def run(name,cmd,node=False):
 mount='/workspace'if node else'/var/www/html';image='node:24-bookworm-slim'if node else'bancay-php:8.3'
 r=subprocess.run(['docker','run','--rm','--network','bancay_default','--user','1000:1000','--env-file',str(checkout/'.env'),'-v',str(checkout)+':'+mount,'-w',mount,image]+cmd,cwd=ROOT,capture_output=True,text=True)
 (P/('clean-'+name+'.log')).write_text(r.stdout+r.stderr);result['steps'].append({'step':name,'exit_code':r.returncode,'status':'PASS'if r.returncode==0 else'FAIL'});print('Clean install',name,r.returncode,flush=True);assert r.returncode==0,name
try:
 run('composer-install',['composer','install','--no-interaction','--prefer-dist'])
 run('vendor-publish',['php','artisan','vendor:publish','--provider=Exceedone\\Exment\\ExmentServiceProvider','--no-interaction'])
 run('foundation-install',['php','artisan','store:install-local','--no-interaction'])
 run('initialize-admin',['php','artisan','store:initialize-local-admin','--no-interaction'])
 run('configure-admin',['php','artisan','store:configure-admin'])
 run('storage-link',['php','artisan','storage:link'])
 run('npm-ci',['npm','ci'],True);run('production-assets',['npm','run','build'],True)
 run('route-cache',['php','artisan','route:cache']);run('config-cache',['php','artisan','config:cache']);run('blade-cache',['php','artisan','view:cache'])
 # Health + empty storefront via the real HTTP kernel in an independent new process.
 (checkout/'clean-smoke.php').write_text("<?php require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$kernel=$app->make(Illuminate\\Contracts\\Http\\Kernel::class);foreach(['/health','/','/sitemap.xml','/admin/auth/login']as$path){$response=$kernel->handle(Illuminate\\Http\\Request::create('http://localhost:8000'.$path));if($response->getStatusCode()!==200){throw new RuntimeException('Clean HTTP smoke failed');}}echo 'PASS';")
 run('http-smoke',['php','clean-smoke.php']);result['status']='PASS'
 result['base_table_count']=int(query(f"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='{database}' AND TABLE_TYPE='BASE TABLE';"))
 result['view_count']=int(query(f"SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='{database}' AND TABLE_TYPE='VIEW';"))
 result['native_table_names']=query(f'SELECT table_name FROM `{database}`.custom_tables ORDER BY table_name;').splitlines()
 result['main_native_table_names']=query('SELECT table_name FROM bancay.custom_tables ORDER BY table_name;').splitlines()
 result['domain_schema_differences']=[]
 for table in ['categories','products','product_images','product_options','product_option_values','product_variants','product_variant_values','inventories','customers','customer_addresses','orders','order_items','order_status_histories','article_categories','articles','banners','settings']:
  a=query(f'SHOW CREATE TABLE `{database}`.`{table}`;');b=query(f'SHOW CREATE TABLE bancay.`{table}`;');a=re.sub(r' AUTO_INCREMENT=\d+','',a);b=re.sub(r' AUTO_INCREMENT=\d+','',b)
  if a!=b:result['domain_schema_differences'].append(table)
 assert not result['domain_schema_differences'],'Domain schema mismatch'
except Exception as e:result['status']='FAIL';result['failed_step']=str(e)
finally:
 query(f"DROP DATABASE `{database}`; DROP USER '{username}'@'%';");result['qa_database_removed']=True;result['credentials_retained_private']=True;(OUT/'clean-install.json').write_text(json.dumps(result,indent=2)+'\n')
print('Clean install:',result['status'],flush=True)
