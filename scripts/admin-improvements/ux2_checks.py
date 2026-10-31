"""Run current checks with separate UX2 artifacts; preserve B1 evidence."""
import checks

checks.OUT = checks.ROOT / 'docs/admin-improvements/ux2/evidence'
checks.PRIVATE = checks.ROOT / 'legacy-private/admin-improvements/ux2'

if __name__ == '__main__':
    checks.main()
