export function normalizeLocationName(name) {
    return String(name || '').trim().toLowerCase().replace(/\s+/g, ' ');
}

export function resolveLocationKey(locations, region) {
    if (!locations || region === undefined || region === null || region === '') {
        return '';
    }

    const raw = String(region);
    if (Object.prototype.hasOwnProperty.call(locations, raw)) {
        return raw;
    }

    const needle = normalizeLocationName(raw);
    return Object.keys(locations).find((key) => normalizeLocationName(key) === needle) || '';
}

export function districtsForRegion(locations, region) {
    const key = resolveLocationKey(locations, region);
    if (!key) {
        return [];
    }

    const districts = locations[key];
    if (Array.isArray(districts)) {
        return districts.filter(Boolean).map((name) => String(name));
    }

    if (districts && typeof districts === 'object') {
        return Object.values(districts).filter(Boolean).map((name) => String(name));
    }

    return [];
}

export function tzAddress(locations, initialRegion, initialDistrict, labels) {
    return {
        locations,
        labels: labels || {},
        savedDistrict: initialDistrict || '',
        region: initialRegion || '',
        district: initialDistrict || '',
        districtOptions: [],
        districtStatus: 'idle',
        init() {
            this.refreshDistricts({ preserveSaved: true });
            this.syncDistrictSelection();
        },
        onRegionChange() {
            this.district = '';
            this.savedDistrict = '';
            this.refreshDistricts();
        },
        refreshDistricts(opts = {}) {
            const preserveSaved = !!opts.preserveSaved;
            this.districtStatus = 'loading';
            const region = this.region;
            if (!region) {
                this.districtOptions = [];
                this.districtStatus = 'idle';
                return;
            }

            try {
                const districts = districtsForRegion(this.locations, region);
                const preserve = preserveSaved ? (this.savedDistrict || this.district) : '';
                if (preserve && !districts.includes(preserve)) {
                    districts.unshift(preserve);
                }

                this.districtOptions = districts;
                this.districtStatus = districts.length ? 'ready' : 'empty';
            } catch (e) {
                this.districtOptions = [];
                this.districtStatus = 'error';
            }
        },
        retryDistricts() {
            this.refreshDistricts({ preserveSaved: true });
        },
        syncDistrictSelection() {
            this.$nextTick(() => {
                if (this.savedDistrict && this.districtOptions.includes(this.savedDistrict)) {
                    this.district = this.savedDistrict;
                }
            });
        },
        districtPlaceholder() {
            if (this.districtStatus === 'loading') {
                return this.labels.loadingDistricts || '';
            }

            return this.labels.selectDistrict || '';
        },
    };
}

export function bindTzAddressGlobally() {
    window.tzAddress = tzAddress;
    window.kfDistrictsForRegion = districtsForRegion;
}
