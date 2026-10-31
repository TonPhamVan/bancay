"""Current UI acceptance checks; historical B1/UX2 evidence stays immutable."""
import checks

checks.OUT = checks.ROOT / 'docs/admin-improvements/ui-acceptance-20261031/evidence'
checks.PRIVATE = checks.ROOT / 'legacy-private/admin-improvements/ui-acceptance-20261031'

if __name__ == '__main__':
    checks.main()
