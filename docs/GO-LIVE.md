# Kopafasta go-live architecture

Three environments. Production only receives a Git commit that already passed Staging/UAT.

| | Local | Staging / UAT | Production |
|---|---|---|---|
| Domain | `127.0.0.1` | `staging.kopafasta.com` | `https://www.kopafasta.com` |
| Droplet | developer machine | smaller, separate | larger, separate |
| `APP_ENV` | `local` | `staging` | `production` |
| Database | local | `kopafasta_staging` | `kopafasta_production` |
| Payments | sandbox / dummy | sandbox | live PayIn |
| SMS / email | log / test | log or allow-list | live |
| SEO | noindex | noindex, nofollow | public pages indexable; private stay noindex |
| Banner | optional | **STAGING** | none |

`kopafasta.com` redirects to `www.kopafasta.com` after cutover. GoDaddy stays the registrar (`ns31/ns32.domaincontrol.com`).

## Current live box (do not disturb yet)

The existing DigitalOcean Droplet (`ubuntu-s-4vcpu-8gb-nyc1`, NYC1, `167.99.239.125`) currently serves **`https://kopafasta.triptz.net`**. It is still the live application until staging is proven and the owner approves DNS cutover.

**Do not** change `www.kopafasta.com` DNS yet. Apex `kopafasta.com` currently resolves to Cloudflare (`172.66.2.113`, `162.159.142.117`), not this Droplet.

## Release process

1. Build locally. Automated tests pass. **Commit.**
2. `STAGING_HOST=root@<staging-ip> ./scripts/deploy-staging.sh` deploys that exact commit (`git archive`, not a dirty working tree).
3. Owner tests on `staging.kopafasta.com`.
4. Fixes: new commit → staging again.
5. Owner: **Accepted / Freeze / Deploy.**
6. `APPROVED_COMMIT=<sha> CONFIRM_PRODUCTION=1 ./scripts/promote-production.sh` promotes **that same SHA**. Staging commit = Production commit.

Rollback of application code: redeploy the previous SHA the same way. Database roll-forward must stay conservative (no casual drops/truncates).

## Seeders

- **Safe configuration:** `SafeConfigurationSeeder` (products, templates, roles, fees). Used on every staging/production deploy.
- **Demo / test:** `CustomerSeeder`, `DemoLoanSeeder`, `DemoAffiliateSeeder`, `PartnerDemoAccountsSeeder`, `MarketplaceAssetSeeder`. Staging/local only.
- **Never** `php artisan db:fresh --seed` on production.

## Isolation checklist

Separate on each Droplet: application directory, `.env`, `APP_KEY`, database + user, `storage/app`, cache prefix, session cookie name, queue connection, logs, PayIn keys + webhook URL, mail/SMS.

Session cookies must stay host-only (`SESSION_DOMAIN=null`) so a staging login cannot authenticate `www.kopafasta.com`.

## DNS (GoDaddy) — after Droplet IPs exist

Inspect existing records first. Preserve DMARC (`_dmarc`) and any SPF/DKIM/MX/TXT. Only add website records.

Until a staging Droplet exists, **do not enter guessed IPs**.

```
Type    Name      Value                         TTL
A       staging   <STAGING_DROPLET_IP>          600
```

Production cutover (later, owner-approved):

```
Type    Name      Value                         TTL
A       @         <PRODUCTION_DROPLET_IP>       600
A       www       <PRODUCTION_DROPLET_IP>       600
```

Then Certbot HTTPS and apex → `https://www.kopafasta.com`.

## Backups (production, before cutover)

Daily `mysqldump` of `kopafasta_production` off-box, plus `storage/app` uploads. Test restore on staging before launch. Record: previous commit, new commit, time, migrations, who deployed, smoke result.
