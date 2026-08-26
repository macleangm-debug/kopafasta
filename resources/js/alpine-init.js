import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { registerFormReadyAlpine } from './form-ready';
import { registerCollateralAddForm } from './collateral-add-form';
import { registerProfileSectionCard } from './profile-section-card';
import { registerSavingOverlay } from './saving-overlay';
import { registerPartnerCreateConfirm } from './partner-create-confirm';

Alpine.plugin(collapse);
window.Alpine = Alpine;
registerFormReadyAlpine(Alpine);
registerCollateralAddForm(Alpine);
registerProfileSectionCard(Alpine);
registerSavingOverlay(Alpine);
registerPartnerCreateConfirm(Alpine);

window.campaignWizard = function campaignWizard(cfg) {
    const initial = cfg.initial || {};
    return {
        estimateUrl: cfg.estimateUrl,
        savedAudiences: cfg.audiences || [],
        intents: cfg.intents || {},
        intent: initial.intent || 'encourage_plus',
        intentOther: initial.intentOther || '',
        audienceMode: initial.audienceMode || 'everyone',
        audienceId: initial.audienceId || '',
        country: initial.country || '',
        status: initial.status || 'active',
        grades: initial.grades || [],
        plus: initial.plus || 'any',
        borrowing: initial.borrowing || 'any',
        affiliate: initial.affiliate || 'any',
        channels: initial.channels && initial.channels.length ? initial.channels : ['in_app'],
        sendMode: initial.sendMode || 'now',
        name: initial.name || '',
        messageEn: initial.messageEn || '',
        messageSw: initial.messageSw || '',
        cta: initial.cta || '',
        offerId: initial.offerId || '',
        estimate: null,
        estimateCompact: null,
        estimateLoading: false,
        get payloadType() {
            if (this.intent === 'promote_offer') return 'offer';
            if (this.intent === 'encourage_plus') return 'plus';
            if (this.intent === 'referral') return 'referral';
            if (this.intent === 'learning_content') return 'article';
            if (this.intent === 'fee_promotion') return 'fee';
            return 'message';
        },
        get intentLabel() {
            if (this.intent === 'other') return this.intentOther || 'Other';
            return this.intents[this.intent] || this.intent;
        },
        get audienceLabel() {
            if (this.audienceMode === 'saved') {
                const row = this.savedAudiences.find((a) => String(a.id) === String(this.audienceId));
                return row ? row.name : 'Saved audience';
            }
            if (this.audienceMode === 'everyone') return 'Everyone eligible';
            return 'Custom targeting';
        },
        get estimateLabel() {
            if (this.estimateLoading) return 'Counting…';
            if (this.estimateCompact) return this.estimateCompact + ' people';
            if (this.estimate === 0) return '0 people';
            return '—';
        },
        toggleGrade(value) {
            if (this.grades.includes(value)) {
                this.grades = this.grades.filter((g) => g !== value);
            } else {
                this.grades = this.grades.concat([value]);
            }
            this.refreshEstimate();
        },
        toggleChannel(value) {
            if (this.channels.includes(value)) {
                this.channels = this.channels.filter((c) => c !== value);
            } else {
                this.channels = this.channels.concat([value]);
            }
        },
        async refreshEstimate() {
            this.estimateLoading = true;
            const params = new URLSearchParams();
            if (this.audienceMode === 'everyone') {
                params.set('status', 'active');
            } else if (this.audienceMode === 'saved') {
                const row = this.savedAudiences.find((a) => String(a.id) === String(this.audienceId));
                this.estimate = row ? row.count : null;
                this.estimateCompact = row ? String(row.count) : null;
                this.estimateLoading = false;
                return;
            } else {
                if (this.country) params.set('country_code', this.country);
                if (this.status) params.set('status', this.status);
                this.grades.forEach((g) => params.append('grades[]', g));
                params.set('plus', this.plus);
                params.set('borrowing', this.borrowing);
                params.set('affiliate', this.affiliate);
            }
            try {
                const response = await fetch(this.estimateUrl + '?' + params.toString(), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await response.json();
                this.estimate = data.count;
                this.estimateCompact = data.compact || String(data.count);
            } catch (e) {
                this.estimate = null;
                this.estimateCompact = null;
            } finally {
                this.estimateLoading = false;
            }
        },
        init() {
            this.refreshEstimate();
            this.$watch('audienceMode', () => this.refreshEstimate());
            this.$watch('audienceId', () => this.refreshEstimate());
            this.$watch('country', () => this.refreshEstimate());
            this.$watch('status', () => this.refreshEstimate());
            this.$watch('plus', () => this.refreshEstimate());
            this.$watch('borrowing', () => this.refreshEstimate());
            this.$watch('affiliate', () => this.refreshEstimate());
        },
    };
};

window.demoCreate = function demoCreate(cfg) {
    return {
        who: 'borrower',
        duration: '60',
        personaKey: (cfg.personas || [])[0]?.key || '',
        scenarioKey: '',
        personas: cfg.personas || [],
        scenarios: cfg.scenarios || [],
        get filteredPersonas() {
            const who = this.who;
            const rows = this.personas.filter((p) => !who || p.role === who || (who === 'plus' && p.role === 'borrower'));
            return rows.length ? rows : this.personas;
        },
        get filteredScenarios() {
            return this.scenarios.filter((row) => (row.roles || []).includes(this.who));
        },
        init() {
            this.$watch('who', () => {
                if (! this.filteredPersonas.find((p) => p.key === this.personaKey)) {
                    this.personaKey = this.filteredPersonas[0]?.key || '';
                }
                if (! this.filteredScenarios.find((s) => s.key === this.scenarioKey)) {
                    this.scenarioKey = this.filteredScenarios[0]?.key || '';
                }
            });
            this.personaKey = this.filteredPersonas[0]?.key || this.personaKey;
            this.scenarioKey = this.filteredScenarios[0]?.key || '';
        },
    };
};

window.adminGlobalSearch = function adminGlobalSearch(url) {
    return {
        open: false,
        q: '',
        groups: [],
        loading: false,
        error: false,
        recents: [],
        activeIndex: -1,
        flat: [],
        openSearch() {
            this.open = true;
            this.loadRecents();
            this.$nextTick(() => this.$refs.input?.focus());
        },
        closeSearch() {
            this.open = false;
            this.activeIndex = -1;
        },
        loadRecents() {
            try {
                this.recents = JSON.parse(localStorage.getItem('kf-admin-search-recents') || '[]');
            } catch (e) {
                this.recents = [];
            }
        },
        remember(item) {
            const next = [{ title: item.title, url: item.url, subtitle: item.subtitle }].concat(
                this.recents.filter((r) => r.url !== item.url)
            ).slice(0, 6);
            this.recents = next;
            localStorage.setItem('kf-admin-search-recents', JSON.stringify(next));
        },
        flatten() {
            this.flat = [];
            this.groups.forEach((group) => {
                (group.items || []).forEach((item) => this.flat.push(item));
            });
        },
        move(delta) {
            if (this.flat.length === 0) return;
            this.activeIndex = (this.activeIndex + delta + this.flat.length) % this.flat.length;
        },
        openActive() {
            const item = this.flat[this.activeIndex] || this.flat[0];
            if (! item) return;
            this.remember(item);
            window.location.href = item.url;
        },
        async run() {
            const q = this.q.trim();
            if (! q) {
                this.groups = [];
                this.flat = [];
                this.activeIndex = -1;
                this.error = false;
                return;
            }
            this.loading = true;
            this.error = false;
            try {
                const response = await fetch(url + '?q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (! response.ok) {
                    throw new Error('search failed');
                }
                const data = await response.json();
                this.groups = data.groups || [];
                this.flatten();
                this.activeIndex = this.flat.length ? 0 : -1;
            } catch (e) {
                this.groups = [];
                this.flat = [];
                this.error = true;
            } finally {
                this.loading = false;
            }
        },
    };
};

function initAlpineTrees() {
    document.querySelectorAll('[x-data]').forEach((el) => {
        if (el._x_dataStack) {
            return;
        }

        try {
            Alpine.initTree(el);
        } catch (error) {
            console.error('Alpine failed to initialize component.', el, error);
        }
    });
}

function startAlpine() {
    if (window.__alpineStarted) {
        return;
    }

    window.__alpineStarted = true;
    Alpine.start();
    initAlpineTrees();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startAlpine);
} else {
    startAlpine();
}
