# Kopafasta — current state (read this first)

Short release memory for economical micropasses. Prefer this file + one relevant handoff brief over rediscovering the whole repo.

## SHAs (four distinct states — do not conflate)

| State | SHA | Note |
| --- | --- | --- |
| **Live production (P0 application-fee ownership)** | `f8b8c80cbba6ab4b824d1b7b680b92ff3747ada5` | Cherry-pick of `5f4a414f` onto prior live `1d7f03ed` only. Mohamed `APP-EM-MU8Q` / `PAY-N1UXNN` obligation=paid. **No Support code.** Owner: confirm guarantor invite without second fee. |
| **Prior live production (Affiliate Premium bleed fix)** | `1d7f03ed052352c4e5b3f1e7c4aab4c299d5a906` | Superseded by fee ownership hotfix above. |
| **Historical production (Affiliate + ladmin hotfix)** | `965296be2dfc2333384acc93bd5f64be08f7b1cf` | Affiliate wizard Continue + `/ladmin/login` alias. Ancestry of `1d7f03ed`. |
| **Accepted production baseline (Release 2 freeze)** | `0b292c681f5c110d33bcc8fe324c9c618ea5b0ac` | Release 2 production-closed ancestry root. |
| **Last locally observed production snapshot** | `91b9942a…` | Historical only (`parity/production-91b9942a`). Not a reason to roll staging backward. |
| **Affiliate staging baseline (Owner UAT)** | `ee5148c5ecd34c4ec12c6b44dcc68f240776982b` | Prior Performance freeze tip. **Superseded for contracts re-acceptance — see tip below.** |
| **Affiliate staging tip (contracts re-accept)** | `50665e33a2351c272976f8132dd13a9a02cf9c67` | Owner EN/SW pack v2; existing Affiliates on older acceptance must re-accept. Historical signed v1 retained. |
| **Affiliate commercial closure (staging tip)** | `6ddc3077` | Commission remaining-amount basis + Accounting earn/payout + public apply fee ON/OFF via payment.show body. Agreement content_revision 3. Staging only. |
| **Partner 360 Review Decision blank P0 (staging tip)** | `ae4747bbb9a458b5dd4809e44b470984ad968c75` | Blank Review Decision body fixed (Alpine @js attribute). Document update/replace on same request path. Staging only. |
| **Affiliate requested-document tracking UX (staging tip)** | `22d60dc0d89b0590f174306f16448c7df22cf13e` | Single Document Holder fulfill surface + tracking result mode. Staging only. |
| **Partner match resolution UX cleanup (staging tip)** | `6b8c73faa5e6b88595c7969f759b2a3439f9cb03` | Dedupe Needs Attention; Open {Name}; workspace switcher; Affiliate landing Sign in CTA. Staging only. |
| **Shared date-input P0 (staging tip)** | `95e7acfae8771db6bef2f4145456ff15cb35296c` | Year pick updates draft; openAnchor≠value; 1989 stays 1989. Staging only. |
| **Affiliate collision resolution (staging tip)** | `c29deedb54930e733965f639f82d7e9f796a5550` | Affiliate Use existing / Keep separate + Change applicant email. PA-3 not approved. Staging only. |
| **Match re-eval flash (staging tip)** | `c2eae91b84fbe1b62a63746e89aa6921cf1a57d5` | Email-change status reflects remaining unresolved matches. Staging only. |
| **Same-person phone identity link (staging tip)** | `9e358a30e2fec8d925f8b771726732aa354670e1` | Owner Link identity → one login, multi-role Partner workspaces. PA-3 not approved. Staging only. |
| **Partner-only identity link guard (staging tip)** | `015644b47013a21b4214e1d048edc18d2e09a949` | Borrower/Member never merges with Partner. PA-3 still pending. Staging only. |
| **Approve decision validation fix (staging tip)** | `44132b1dcc83bd03e3c8f04871b61ff3bd9e1b18` | Document/Information required only for Request information. PA-3 Link preserved, not approved by deploy. Staging only. |
| **Owner UAT hotfix Affiliate+guarantor+signatures (staging tip)** | `ea51e69c2dc640a53827cd84575a423c1828c0cf` | Redirect loop, decision read-only, track, Affiliate 360 link tooling, guarantor View, group signature cards. Staging only. |
| **Owner UAT correction Partner roles+Affiliate 360+guarantor (staging tip)** | `3344e1a92b673fc31605e789cb319180fa9ebccc` | Workspace switcher visible; Affiliate 360 not application; false Asset supplier strip; track Vendor typing; profile signature reuse; guarantor View hero. Staging only. |
| **Affiliate RC workspace+activation+360 roles (staging tip)** | `578ecac34ea1592e0ca2d87fe5909eebbc72745d` | Compact header workspace switcher; Activate vs Login track CTA; Fuatilia secondary button; Partner 360 multi-role chips. Staging only. Do not promote yet. |
| **Affiliate release landing CTA (staging tip)** | `7421ba332dff2f5f1ff8abfd95081c4f1ae47bdc` | Anza Sasa + Ingia side-by-side on landing hero. Promoted with Affiliate release. |
| **Admin User/Role switcher foundation (staging tip)** | `80a2179104eca1370e41a2a16937f33ab62f194c` | Admin header search → View profile or Enter role workspace; Viewing banner + Exit; audit stays Admin. Staging only. |
| **Account/Role role-first + unified Support (staging tip)** | `1ba4514bf699ff32746e0568fcb429c52be16a47` | Click role → workspace (no person first); agent+partner_support → Support; in-workspace All/staff filter. Support foundation preserved. Staging only — STOP for Owner UAT. |
| **Support UX + UAT conversations (staging tip)** | `47d10060d4628926f5e4cf35e4010d845037b47b` | WhatsApp Inbox, Speak to Support, Case escalate/resolve, quick replies SW-first, Notifications nav removed, SupportStagingUatSeeder. Feature `d6cec2cb`. Staging only — STOP for Owner E2E Support UAT. Production NO. |
| **Support E2E Owner UX correction (staging tip)** | `36e6d99a3c171c4d97b7ee64eacdf38a7d1216db` | FAQ-first member Support, Habari inbox fix, Waiting→Assigned intro, compact 20/52/28 Inbox, resolve-without-case, phone interaction, specialist internal response. Staging only — STOP Owner UAT. Production NO. |
| **Support Inbox density + readable type (staging tip)** | `d7baed911ae5d9292a01647fed007b79b89f46a8` | Narrower chat column (`max-w-6xl`, ~26/42/32), capped bubbles, larger type on CS Inbox + member/partner `ai-support-chat` + help hub widget. Code `633e2825`. Staging only — STOP Owner UAT. Production NO. |
| **Final Owner UAT Support closure (staging tip)** | `7e74bec403111a3f80df03078f89bc0286af2a03` | Resolve→closed + rating card; Help Help/Active/History; agent picker; SLA Settings Hub; six KPIs; performance charts; Viewing Team/agent; recurring flags; KPF-TKT. Baseline `b1ef82ab`. **Superseded by Support+Mohamed UAT closure tip below.** |
| **Support + Mohamed UAT closure (staging tip)** | `9a481cd553303d8f92075936ba86309bad90f283` | KPF-CNV refs; Help Active/History premium cards + Continue; 3-line composer; SW rating notifications; rating→History; MacLean Mohamed-scenario under `+255715222132` / `APP-UAT-MOH-MACLEAN-01`; mobile feedback nested sheet. **Superseded by Guarantor+Support final correction tip below.** |
| **Guarantor + Support final Owner-UAT correction (staging tip)** | `2ee46a9032f496ded83ff9d313175cbc70da8047` | Guarantor invite premium header + real quote terms; quote-change reconfirm; Application View waiting-guarantor (no false pass); declined→Choose another; View ticket CTA; waiting FIFO + keep messaging; Offline Accept guard; Support Home longest-wait KPI. **Superseded by wiring tip below.** |
| **Guarantor data wiring + Support UI closure (staging tip)** | `1873e2436ac3209440d84ba88af02a6d3f63f312` | Invite terms from authoritative submitted quote (no TBD when data exists); replacement attaches as current guarantor; Finish CTA (no resubmit/fee); decline confirm then optional Jiunge; Support Home KPI/Online/Team + Reports Top Issues compact. **Superseded by functional closure tip below.** |
| **Guarantor + Support final functional closure (staging tip)** | `0f9b7a446b97ea61bd6aff8458bb222f30014964` | Code `8544d522`. Reject atomic; Finish→Application View; hide false Ukaguzi after nomination; Edit≠Replace; Online/Offline direct toggle; Support Home premium cards; New Ticket Member ID + redirect list; Start conversation on manual tickets; SW Maswali. Baseline `f8d3cbca`. **Superseded by guarantor UX polish tip below.** |
| **One guarantor/member progress state UI (staging tip)** | `404faa3c7a30f9e3e873027938314d343bc2fd16` | Remove duplicate Inasubiri/Tutaendelea copy; badge follows furthest achieved state; reuse `x-site.invitee-progress` for Group Loan members. Code `966bfebd`. **Superseded by Edit/Supplement final UX tip below.** |
| **Guarantor Edit/Supplement final UX + App View (staging tip)** | `4a3b65b1b977006471a73f117abc0ee2152f52da` | Code `33ddce62`. Save & send → Application View (no Finish); inline guarantor errors (no blocking modals); Hariri loads current; name + muted phone; progress without name repeat; hide Wadhamini wa awali from borrower. Hariri ≠ Chagua. **Superseded by Edit source + Save/Share tip below.** |
| **P0 Guarantor Edit source + Save/Share (staging tip)** | `a8018bd513eb9f9b855b0f4ef225888fafbdeeab` | Edit loads Application View current invitee (not stale Guarantor row); Save changes → share step → Done; Hariri locked after accept (server-enforced). **Superseded by Edit final polish tip below.** |
| **Guarantor UX closure + Screening fixture (staging tip)** | `PLACEHOLDER` | Notification CTAs; App View progress→actions; premium Loan Overview; Borrower+Partner shell scrollbar-gutter parity; Steward `APP-IL-LQU6` in Screening. Baseline `6dcec23d`. **STAGING ONLY — STOP Owner UAT. Production `f8b8c80c` UNCHANGED. DO NOT PROMOTE.** |
| **Guarantor + Group request UX unify (staging tip)** | `2ff12f326b513c57e1358763efea14d5eebc2086` | No auto request modal; one Accept; premium guarantor/group request pages; liability without fake TZS 0; App View one progress card + furthest badge; Screening docs as premium `+` cards. Baseline `b89ab483`. **Superseded by Guarantor UX closure tip above.** |
| **Invited borrower activation unify (staging tip)** | `319739ca2e40fe0ad4527168ec73a4eda5ad2ed7` | Guarantor + external Group Member = ordinary Borrower shell; Welcome+invitation notifs; Mikopo counters; 5-step progress with Akaunti imefunguliwa; TZ membership bypass both journeys. **Superseded by Guarantor + Group request UX unify tip above.** |
| **Guarantor TZ membership skip (staging tip)** | `730b1d2dbc08fd61eb4c1ca7029805f060bce030` | After PIN, TZ guarantors go to borrower dashboard — never `/membership/renew` (membership country-off). **Superseded by invited borrower activation tip above.** |
| **Guarantor register 500 reclaim (staging tip)** | `25801115b3ca7cfaefd6c55600e2601a3e1990af` | Trusted invite registration reclaims stale `guarantor_customer_id` when phone matches invite; InvalidArgumentException → inline error (no 500). **Superseded by TZ membership skip tip above.** |
| **Guarantor Edit final polish (staging tip)** | `4fd63ecf13461ee2c5fe2d334f6b1c8fb5f1a44a` | One field-level validation only; Maliza loading/disabled; remove register_banner on guarantor account creation. Core edit flow PASS. **Superseded by register 500 reclaim tip above.** |
| **Guarantor UX polish (staging tip)** | `0bfd50957083bbe17b213aa03129c45ecb23ee1d` | Declined redundant copy removed; Edit→wizard; one-line invite actions; premium progress journey; borrower in-app notify on accept. Baseline `95514f5f`. **Superseded by progress state UI tip above.** |
| **Closure pass — Application View + Support infrastructure (staging tip)** | `b1ef82ab4c47f9fe336efb09a7e3fb11d5699435` | Code `9a0079c2`. Lending Application View/Wadhamini/lock + Support taxonomy/similar tickets. UAT personas seeded. Superseded for Support leftovers by tip above. |
| **P0 application-fee ownership (staging tip + production hotfix)** | staging `088562b0` / production `f8b8c80c` | Fee owned by application (`5f4a414f`). Production isolated cherry-pick live. Mohamed obligation=paid. Broader Application View is this closure pass. |
| **Support Pass 3 Help Center + queue + ticketing + feedback (staging tip)** | `5852394fbee5e7a16ec123fcef8830525c2ffe89` | Help Center entry; waiting Accept-only + KPIs/timers; Admin staff picker; KPF-TKT Ticket 360 + SLA; mandatory gold-star rating; feedback modal/sheet. Baseline `1b546817`. Staging only — STOP Owner UAT. Production NO. Support 3.1 structured Issue taxonomy deferred behind this P0. |
| **Support Pass 3 lifecycle closure (staging tip)** | `1b54681733979cf523bbc3ecf2835f43fff9046b` | Support Home (no auto-chat); FAQ/HOW TO groups; team queue before agent; resolve→rating→history→new thread; case/create/escalation polish; authenticated feedback; `?` help into Support; searchable templates; EAT timestamps. Baseline `0e1701f6`. Superseded by Help Center tip above. |
| **Support P0.1 reliable chat + desk + interactions + staff edit (staging tip)** | `0e1701f6ffa2528d9df3ce83bb02257d61c3a904` | Same-conversation Member↔Support; shared bubbles; full-width Inbox; New interaction Member/Non-member (no auto-case); canonical staff edit + Reset password; premium Member chat header “Kopafasta Support”. Superseded by Pass 3 tip above. |
| **Support P0 chat delivery + layout hotfix (staging tip)** | `5606e0502f4328264a7b60fd7d37732dc9f05df4` | Restore full Admin width 24/48/28; Accept assigns real agent; Support↔Member poll; fit-content bubbles; intro footer first-only. Code `4c957c0f`. Superseded by P0.1 tip above. |
| **Customer Support workspace foundation (staging tip)** | `fe48034cc7763ead356fa3769328bbf508d04ea1` | Prior CS shell foundation. Superseded by role-first + unified Support tip. |
| **Admin Account/Role staff-first selector (staging tip)** | `902eee124e84afac86df40ffc2c0fc2bb8b731a2` | Staff role rows + All roles filter. Superseded by role-first tip. |
| **Admin Account/Role switcher on Admin page (staging tip)** | `fefd80abf91ce81ec488adc650c96c6657b2aad3` | Reuses `80a21791` in Admin header + EN/SW Account/Role labels. Includes Affiliate/ladmin hotfixes ancestry. Superseded by staff-first selector tip. |
| **Clean production candidate** | `6cb7798c67cc5ccc4581c00c7339155f07a203ea` | Branch `release/production-candidate-accepted`: `4db04d90` + Activity dropdown teleport only. **Excludes Support V1 and latest Family/Kin work. STOP for owner approval before production.** |
| **GitHub `origin/main`** | `bf292772…` (this machine) | Behind local staging tip. Do not force-catch-up with mixed WIP. |

**Typo note:** `73fbbfbe` does not exist — use `73fbbbfe`.

**Rule:** Staging only until owner UAT. Production requires `CONFIRM_PRODUCTION=1` + `APPROVED_COMMIT=<staging sha>`. Never push mixed staging tip to `origin/main` merely to sync GitHub. **Do not promote staging HEAD wholesale.**

## Frozen

- **AG-1**, **AB-1**, PayIn, Accounting, Marketplace, product ordering, Sharia
- Registration password removed this pass (phone + PIN + incomplete draft resume)
- Document holder status cleanup, KYC 405, native Upload chooser, Remove confirmation
- Face focus/reload loop fix (preserve no-snap)
- Partner Experience Consistency (CLOSED) except this owner-authorized Premium Affiliate commercial pass
- **Release 2 lending** (First Gate, Parked Screening, auto-rejection, Screening wizard, System Sorted, receipts, Borrower Loans IA, application restoration). Do not start Steward. **APP-EM-MU8Q restored/closed — do not re-restore.**
- **Payment.show** — closed on production `03beb59b`
- **Affiliate Performance** — PASS/freeze on staging `ee5148c5` (do not polish further unless a visual walkthrough finds a real defect)
- Recovery, Supplier, Valuer, Capital Partner, payment posting/provider logic

## Premium Affiliate (current isolated pass)

Settings Hub → Affiliates → Rates is the **one** default for Standard and Premium. Only Premium may store an individual negotiated override. Affiliate 360 is the commercial-arrangement card. The existing contract renderer snapshots the effective terms. No second portal, commission engine, or Premium rate configuration.

**Owner UAT focus (staging `ee5148c5`):** Home → Performance → Share & Earn → Reports → Profile/360 → Agreement/contract → Wallet/withdrawal → notifications. Watch Premium classification, negotiated-vs-Settings rates, minimum withdrawal, EN/SW, mobile, dark mode. Contracts implemented; finish walkthrough before any production promote. Accumulate genuine defects only — no further Performance polish.

## Canonical rules (this pass)

1. **Region → District:** `x-site.address-fields` only (Profile-identical: mobile bottom sheet + desktop select). AG Overview hides ward/street.
2. **Continue:** Quote pattern — required Overview (`Region`, `District`, activity fields) + farm/land docs → footer Endelea. Optional docs never block.
3. **Three camera families only:**
   - **Shared general shell** `x-site.multi-page-document-upload` — Profile docs, AG docs, farm/activity photos, residence/income/supporting/guarantor/group/partner ordinary Camera CTAs.
   - **Identity directive** — facial/selfie + NIDA front/back (`mode=single` / `capture=selfie|nida`).
   - **Collateral directive** — asset photographs.
4. **Same shell, output by field:** `outputMode=pdf` → one PDF; `capture=images` / `outputMode=images` → image collection (no forced PDF).
5. **Replace:** never asks document type — `startReplace()` → Upload/Camera. Only **Ongeza hati** opens the type picker.
6. **Clean capture:** `fresh: true` / `clear-capture` / `resetCapture()`.
7. **Camera UI:** bottom control area (shutter / facing / add / orientation) + portrait/landscape dotted guide; Finish primary.
8. **NIDA number:** confirm before lock; images remain replaceable. Face: replaceable + Remove uses confirmForm.
9. **Platform Profile UX (frozen pattern):** Collapsed → View all saved fields → Hariri/Edit → `kfAutosave`. One top-centre green tab only (`Inahifadhi…` / ✓ `Imehifadhiwa` / `Haijahifadhiwa · Jaribu tena`). No inline Saved labels. Autosave every valid change (partial OK); Complete ≠ Saved. Signature owns its own focus.
10. **National ID:** one member-facing holder; Front → Back directed; landscape id-card guide; Replace picks side then source.
11. **Public company contact:** one primary email + one primary phone from Settings Hub Company Profile → `support_emails()` / `support_phones()` → footer.
12. **Support V1:** `SupportTicket` + guest fields + round-robin `agent` assignment + `support_ticket_events`. No SupportUser. Staging only until UAT.

## Deploy

```bash
SSH_IDENTITY_FILE=~/.ssh/kopafasta_server \
STAGING_HOST=root@159.89.53.253 \
DEPLOY_COMMIT=<sha> \
./scripts/deploy-staging.sh
```

Never promote production from a SHA the owner has not UAT-passed.
