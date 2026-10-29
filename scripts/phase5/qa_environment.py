"""Own a disposable Phase5 UI clone using established guarded Phase4 clone setup."""
import pathlib,subprocess,sys,shutil
ROOT=pathlib.Path(__file__).resolve().parents[2];sys.path.insert(0,str(ROOT/'scripts/phase4'));import ui_environment as ui
assert sys.argv[1:] in [['start'],['stop']]
mode=sys.argv[1];sys.argv=[sys.argv[0],'start-combined'if mode=='start'else'stop'];ui.main()
if mode=='start':
 qa=ROOT/'legacy-private/phase4/ui';shutil.copytree(ROOT/'storage/app/public',qa/'storage/app/public',dirs_exist_ok=True)
 subprocess.run(['docker','rm','-f',ui.NAME],cwd=ROOT,capture_output=True,check=True)
 subprocess.run(['docker','run','-d','--name',ui.NAME,'--network','bancay_default','--user','1000:1000','--env-file',str(qa/'staging.env'),'-p','127.0.0.1:8003:8000','-v',str(ROOT)+':/var/www/html','-v',str(qa/'storage')+':/var/www/html/storage','-w','/var/www/html','bancay-php:8.3','php','artisan','serve','--host=0.0.0.0','--port=8000','--no-reload'],cwd=ROOT,capture_output=True,check=True)
 print('Phase5 private writable QA media prepared; operational media unchanged')
