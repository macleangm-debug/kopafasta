<script>
(function () {
    function formatTzs(v) {
        return 'TZS ' + new Intl.NumberFormat('en-US').format(Math.round(v || 0));
    }

    function estimateEmi(principal, monthlyRate, months) {
        if (principal <= 0 || months <= 0) return 0;
        if (monthlyRate <= 0) return Math.round(principal / months);
        var pow = Math.pow(1 + monthlyRate, months);
        return Math.round(principal * monthlyRate * pow / (pow - 1));
    }

    function readJson(id, fallback) {
        var el = document.getElementById(id);
        if (! el) return fallback;
        try {
            return JSON.parse(el.textContent || '');
        } catch (e) {
            console.error('Loan origination wizard: failed to parse JSON for #' + id, e);
            return fallback;
        }
    }

    function initLoanOriginationWizard(root) {
        if (root.dataset.ready === '1') return;

        var bootError = root.querySelector('[data-wizard-boot-error]');
        function fail(message) {
            console.error('Loan origination wizard:', message);
            if (bootError) {
                bootError.hidden = false;
                bootError.textContent = message;
            }
        }

        try {
            var products = readJson('loan-origination-products', []);
            if (! Array.isArray(products)) products = [];
            var wizardDataUrl = root.dataset.wizardDataUrl || '';

            var stepEls = Array.from(root.querySelectorAll('[data-step]'));
            var nav = root.querySelector('[data-wizard-nav]');
            var backBtn = root.querySelector('[data-wizard-back]');
            var nextBtn = root.querySelector('[data-wizard-next]');
            var submitBtn = root.querySelector('[data-wizard-submit]');
            var form = root.querySelector('[data-wizard-form]');
            var customerSelect = form ? form.querySelector('[name="customer_id"]') : null;
            var applicationIdInput = form ? form.querySelector('[data-application-id]') : null;
            var interestRateInput = form ? form.querySelector('[data-interest-rate]') : null;
            var productIdInput = form ? form.querySelector('[data-product-id]') : null;
            var productCards = root.querySelector('[data-product-cards]');
            var applicationCards = root.querySelector('[data-application-cards]');
            var applicationEmpty = root.querySelector('[data-application-empty]');
            var applicationSummary = root.querySelector('[data-application-summary]');
            var skipApplicationBtn = root.querySelector('[data-skip-application]');
            var quotePanel = root.querySelector('[data-quote-panel]');
            var eligibilityPanel = root.querySelector('[data-eligibility-panel]');
            var eligibilityList = root.querySelector('[data-eligibility-list]');
            var eligibilityBadge = root.querySelector('[data-eligibility-badge]');
            var profileList = root.querySelector('[data-profile-list]');
            var reviewSummary = root.querySelector('[data-review-summary]');

            if (! form || ! nextBtn || ! backBtn || ! submitBtn || ! nav || stepEls.length === 0) {
                fail('The loan wizard failed to load. Please refresh the page.');
                return;
            }

            var step = 0;
            var total = stepEls.length;
            var selectedProduct = null;
            var selectedApplication = null;
            var manualTerms = false;
            var navButtons = [];
            var customerData = { eligibility: null, profile: null, applications: [] };

            function findProductById(id) {
                return products.find(function (p) { return String(p.id) === String(id); }) || null;
            }

            if (productCards) {
                products.forEach(function (p) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'snap-start shrink-0 w-64 text-left rounded-xl border-2 border-gray-200 p-4 hover:border-amber-300 transition';
                    btn.innerHTML =
                        '<span class="text-[10px] font-mono font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">' + (p.code || '') + '</span>' +
                        '<div class="mt-2 font-semibold text-sm text-gray-900">' + p.name + '</div>' +
                        '<p class="text-[11px] text-gray-500 mt-1 line-clamp-2">' + (p.desc || 'Flexible terms from settings.') + '</p>' +
                        '<div class="text-[11px] text-gray-600 mt-2">' + formatTzs(p.min) + ' – ' + formatTzs(p.max) + '</div>';
                    btn.addEventListener('click', function () {
                        manualTerms = true;
                        if (applicationIdInput) applicationIdInput.value = '';
                        selectedApplication = null;
                        if (applicationSummary) applicationSummary.classList.add('hidden');
                        selectProduct(p);
                        highlightProductCard(btn);
                    });
                    productCards.appendChild(btn);
                });
            }

            function highlightProductCard(activeBtn) {
                if (! productCards) return;
                productCards.querySelectorAll('button').forEach(function (b) {
                    b.classList.remove('border-amber-500', 'ring-2', 'ring-amber-200', 'bg-amber-50');
                    b.classList.add('border-gray-200');
                });
                if (activeBtn) {
                    activeBtn.classList.add('border-amber-500', 'ring-2', 'ring-amber-200', 'bg-amber-50');
                    activeBtn.classList.remove('border-gray-200');
                }
            }

            function selectProduct(p, presetAmount, presetTenure) {
                selectedProduct = p;
                if (productIdInput) productIdInput.value = p.id;
                if (interestRateInput) interestRateInput.value = p.rate;
                if (quotePanel) quotePanel.classList.remove('hidden');
                var amountRange = form.querySelector('[data-range-amount]');
                var tenureRange = form.querySelector('[data-range-tenure]');
                if (! amountRange || ! tenureRange) return;
                amountRange.min = p.min;
                amountRange.max = p.max;
                amountRange.step = Math.max(50000, Math.round((p.max - p.min) / 100) || 50000);
                amountRange.value = Math.max(p.min, Math.min(p.max, presetAmount || parseFloat(form.querySelector('[data-input-amount]').value) || p.min));
                tenureRange.min = p.tmin;
                tenureRange.max = p.tmax;
                tenureRange.value = Math.max(p.tmin, Math.min(p.tmax, presetTenure || parseInt(form.querySelector('[data-input-tenure]').value, 10) || p.tmin));
                updateQuote();
            }

            function selectApplication(app) {
                selectedApplication = app;
                manualTerms = false;
                if (applicationIdInput) applicationIdInput.value = app.id;
                var p = findProductById(app.product_id);
                if (applicationSummary) {
                    applicationSummary.classList.remove('hidden');
                    applicationSummary.innerHTML = '<strong>Linked application:</strong> ' + app.application_number +
                        ' · ' + (app.product_name || 'Product') + ' · ' + formatTzs(app.amount) + ' · ' + app.tenure + ' months';
                }
                if (p) {
                    selectProduct(p, app.amount, app.tenure);
                    if (productCards) {
                        var matchBtn = null;
                        productCards.querySelectorAll('button').forEach(function (btn, idx) {
                            if (products[idx] && String(products[idx].id) === String(p.id)) matchBtn = btn;
                        });
                        highlightProductCard(matchBtn);
                    }
                }
                if (applicationCards) {
                    applicationCards.querySelectorAll('button').forEach(function (b) {
                        b.classList.remove('border-emerald-500', 'ring-2', 'ring-emerald-200', 'bg-emerald-50');
                        b.classList.add('border-gray-200');
                    });
                }
            }

            function updateQuote() {
                if (! selectedProduct) return;
                var amountRange = form.querySelector('[data-range-amount]');
                var tenureRange = form.querySelector('[data-range-tenure]');
                if (! amountRange || ! tenureRange) return;
                var amount = parseFloat(amountRange.value);
                var tenure = parseInt(tenureRange.value, 10);
                form.querySelector('[data-input-amount]').value = amount;
                form.querySelector('[data-input-tenure]').value = tenure;
                var amountEl = root.querySelector('[data-quote-amount]');
                var tenureEl = root.querySelector('[data-quote-tenure]');
                if (amountEl) amountEl.textContent = formatTzs(amount);
                if (tenureEl) tenureEl.textContent = tenure;
                var emi = estimateEmi(amount, selectedProduct.rate, tenure);
                var interest = Math.max(0, (emi * tenure) - amount);
                var emiEl = root.querySelector('[data-quote-emi]');
                var weeklyEl = root.querySelector('[data-quote-weekly]');
                var interestEl = root.querySelector('[data-quote-interest]');
                var totalEl = root.querySelector('[data-quote-total]');
                if (emiEl) emiEl.textContent = formatTzs(emi);
                if (weeklyEl) weeklyEl.textContent = formatTzs(emi / 4.33);
                if (interestEl) interestEl.textContent = formatTzs(interest);
                if (totalEl) totalEl.textContent = formatTzs(emi * tenure);
            }

            var amountRange = form.querySelector('[data-range-amount]');
            var tenureRange = form.querySelector('[data-range-tenure]');
            if (amountRange) amountRange.addEventListener('input', updateQuote);
            if (tenureRange) tenureRange.addEventListener('input', updateQuote);

            function renderApplications() {
                if (! applicationCards) return;
                applicationCards.innerHTML = '';
                var apps = customerData.applications || [];
                if (applicationEmpty) {
                    applicationEmpty.classList.toggle('hidden', apps.length > 0);
                }
                apps.forEach(function (app) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'text-left rounded-xl border-2 border-gray-200 p-4 hover:border-emerald-300 transition';
                    btn.innerHTML =
                        '<span class="text-[10px] font-mono font-semibold text-emerald-800 bg-emerald-100 px-1.5 py-0.5 rounded">' + app.application_number + '</span>' +
                        '<div class="mt-2 font-semibold text-sm text-gray-900">' + (app.product_name || 'Loan application') + '</div>' +
                        '<div class="text-xs text-gray-600 mt-2">' + formatTzs(app.amount) + ' · ' + app.tenure + ' months</div>' +
                        '<p class="text-[11px] text-emerald-700 mt-2 font-semibold">Use this application →</p>';
                    btn.addEventListener('click', function () {
                        selectApplication(app);
                        btn.classList.add('border-emerald-500', 'ring-2', 'ring-emerald-200', 'bg-emerald-50');
                        btn.classList.remove('border-gray-200');
                    });
                    applicationCards.appendChild(btn);
                });
            }

            function renderEligibility() {
                if (! eligibilityPanel) return;
                var data = customerData.eligibility;
                if (! data) {
                    eligibilityPanel.classList.add('hidden');
                    return;
                }
                eligibilityPanel.classList.remove('hidden');
                if (eligibilityBadge) {
                    eligibilityBadge.textContent = data.can_apply ? 'Profile ready' : 'Requirements incomplete';
                    eligibilityBadge.className = 'text-xs font-semibold rounded-full px-2.5 py-1 ' +
                        (data.can_apply ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800');
                }
                if (eligibilityList) {
                    eligibilityList.innerHTML = (data.items || []).map(function (item) {
                        var icon = item.complete ? '<span class="text-emerald-600">✓</span>' : '<span class="text-amber-600">□</span>';
                        return '<li class="flex gap-2 items-start">' + icon + '<span><strong>' + item.label + '</strong><br><span class="text-xs text-gray-500">' + item.detail + '</span></span></li>';
                    }).join('');
                }
            }

            function renderProfile() {
                if (! profileList) return;
                var sections = customerData.profile || [];
                if (! sections.length) {
                    profileList.innerHTML = '<li class="text-sm text-gray-500 sm:col-span-2">Select a customer first.</li>';
                    return;
                }
                profileList.innerHTML = sections.map(function (s) {
                    return '<li class="rounded-lg ring-1 ' + (s.complete ? 'ring-emerald-200 bg-emerald-50' : 'ring-amber-200 bg-amber-50') + ' px-4 py-3">' +
                        '<p class="text-sm font-semibold ' + (s.complete ? 'text-emerald-900' : 'text-amber-900') + '">' +
                        (s.complete ? '✓ ' : '□ ') + s.label + '</p>' +
                        '<p class="text-xs mt-0.5 ' + (s.complete ? 'text-emerald-700' : 'text-amber-700') + '">' + s.detail + '</p></li>';
                }).join('');
            }

            function renderReview() {
                if (! reviewSummary || ! customerSelect) return;
                var customerLabel = customerSelect.options[customerSelect.selectedIndex]
                    ? customerSelect.options[customerSelect.selectedIndex].text
                    : '—';
                var rows = [
                    ['Customer', customerLabel],
                    ['Application', selectedApplication ? selectedApplication.application_number : (manualTerms ? 'Manual terms (no application)' : '—')],
                    ['Product', selectedProduct ? selectedProduct.name : '—'],
                    ['Principal', root.querySelector('[data-quote-amount]') ? root.querySelector('[data-quote-amount]').textContent : '—'],
                    ['Tenure', (root.querySelector('[data-quote-tenure]') ? root.querySelector('[data-quote-tenure]').textContent : '—') + ' months'],
                    ['Loan status', 'Pending disbursement'],
                ];
                reviewSummary.innerHTML = rows.map(function (r) {
                    return '<div><dt class="text-xs text-gray-500">' + r[0] + '</dt><dd class="font-medium text-gray-900 mt-0.5">' + r[1] + '</dd></div>';
                }).join('');
            }

            function loadCustomerData(customerId) {
                customerData = { eligibility: null, profile: null, applications: [] };
                selectedApplication = null;
                manualTerms = false;
                if (applicationIdInput) applicationIdInput.value = '';
                if (applicationSummary) applicationSummary.classList.add('hidden');
                renderEligibility();
                renderProfile();
                renderApplications();
                if (! customerId || ! wizardDataUrl) return;
                fetch(wizardDataUrl.replace('__ID__', encodeURIComponent(customerId)), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                    .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
                    .then(function (data) {
                        customerData.eligibility = data.eligibility || null;
                        customerData.profile = data.profile || null;
                        customerData.applications = data.applications || [];
                        renderEligibility();
                        renderProfile();
                        renderApplications();
                    })
                    .catch(function () {
                        if (eligibilityList) {
                            eligibilityList.innerHTML = '<li class="text-sm text-amber-700">Could not load borrower data.</li>';
                            eligibilityPanel.classList.remove('hidden');
                        }
                    });
            }

            if (customerSelect) {
                customerSelect.addEventListener('change', function () {
                    loadCustomerData(customerSelect.value);
                });
            }

            if (skipApplicationBtn) {
                skipApplicationBtn.addEventListener('click', function () {
                    manualTerms = true;
                    selectedApplication = null;
                    if (applicationIdInput) applicationIdInput.value = '';
                    if (applicationSummary) applicationSummary.classList.add('hidden');
                    var productStep = stepEls.findIndex(function (el) { return el.dataset.stepKey === 'product'; });
                    if (productStep >= 0) {
                        step = productStep;
                        render();
                    }
                });
            }

            stepEls.forEach(function (el, index) {
                var label = el.dataset.stepLabel || ('Step ' + (index + 1));
                var wrap = document.createElement('div');
                wrap.className = 'flex items-center gap-2 shrink-0';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border bg-white text-gray-600 border-gray-300';
                btn.innerHTML = '<span class="size-5 grid place-items-center rounded-full text-[11px] bg-gray-100">' + (index + 1) + '</span><span>' + label + '</span>';
                btn.addEventListener('click', function () {
                    if (index <= step) {
                        step = index;
                        render();
                    }
                });
                wrap.appendChild(btn);
                navButtons.push({ btn: btn, index: index });
                if (index < total - 1) {
                    var arrow = document.createElement('span');
                    arrow.className = 'text-gray-300';
                    arrow.textContent = '→';
                    wrap.appendChild(arrow);
                }
                nav.appendChild(wrap);
            });

            function stepKey() {
                return stepEls[step] ? stepEls[step].dataset.stepKey : '';
            }

            function validateStep() {
                if (stepKey() === 'borrower' && customerSelect && ! customerSelect.value) {
                    alert('Please select a customer.');
                    return false;
                }
                if (stepKey() === 'application') {
                    return true;
                }
                if (stepKey() === 'product') {
                    if (! productIdInput || ! productIdInput.value) {
                        alert('Please select a loan product.');
                        return false;
                    }
                    if (! form.querySelector('[data-input-amount]').value) {
                        alert('Please set the loan amount.');
                        return false;
                    }
                }
                return true;
            }

            function render() {
                stepEls.forEach(function (el, index) {
                    var show = index === step;
                    el.hidden = ! show;
                    el.classList.toggle('hidden', ! show);
                });
                backBtn.hidden = step === 0;
                nextBtn.hidden = step >= total - 1;
                submitBtn.hidden = step !== total - 1;

                navButtons.forEach(function (item) {
                    var state = item.index < step ? 'done' : (item.index === step ? 'active' : 'upcoming');
                    item.btn.className = 'flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-semibold border transition ' +
                        (state === 'active' ? 'bg-amber-600 text-white border-amber-600' :
                            (state === 'done' ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-white text-gray-600 border-gray-300'));
                });

                var key = stepKey();
                if (key === 'application') renderApplications();
                if (key === 'profile') renderProfile();
                if (key === 'review') renderReview();
            }

            nextBtn.addEventListener('click', function () {
                if (! validateStep()) return;
                if (stepKey() === 'application' && selectedApplication) {
                    var productStep = stepEls.findIndex(function (el) { return el.dataset.stepKey === 'product'; });
                    step = productStep >= 0 ? productStep : step + 1;
                    render();
                    return;
                }
                if (step < total - 1) {
                    step++;
                    render();
                }
            });

            backBtn.addEventListener('click', function () {
                if (step > 0) {
                    step--;
                    render();
                }
            });

            if (customerSelect && customerSelect.value) loadCustomerData(customerSelect.value);
            render();
            root.dataset.ready = '1';
        } catch (e) {
            fail('The loan wizard failed to load. Please refresh the page.');
            console.error(e);
        }
    }

    function boot() {
        document.querySelectorAll('.admin-loan-origination-wizard').forEach(initLoanOriginationWizard);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
