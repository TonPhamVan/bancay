"""Generate local secrets without displaying them; never overwrite existing .env."""

import base64
from pathlib import Path
import secrets


root = Path(__file__).resolve().parent.parent
env = root / '.env'
if not env.exists():
    content = (root / '.env.example').read_text()
    content = content.replace('APP_KEY=\n', 'APP_KEY=base64:' + base64.b64encode(secrets.token_bytes(32)).decode() + '\n')
    content = content.replace('DB_PASSWORD=\n', 'DB_PASSWORD=' + secrets.token_hex(24) + '\n')
    content = content.replace('DB_ROOT_PASSWORD=\n', 'DB_ROOT_PASSWORD=' + secrets.token_hex(24) + '\n')
    env.write_text(content)
    env.chmod(0o600)
    print('Created private local .env; no secret values displayed.')
else:
    print('Existing .env preserved.')

private = root / 'legacy-private' / 'phase1'
private.mkdir(parents=True, exist_ok=True)
(root / 'legacy-private').chmod(0o700)
private.chmod(0o700)
admin = private / 'admin.env'
if not admin.exists():
    admin.write_text('BANCAY_BOOTSTRAP_PASSWORD=' + secrets.token_urlsafe(24) + '\n')
    admin.chmod(0o600)
print('Local bootstrap password file: legacy-private/phase1/admin.env (ignored by Git).')
