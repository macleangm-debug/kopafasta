export function tzAddress(locations, initialRegion, initialDistrict, labels) {
    return {
        locations,
        labels: labels || {},
        savedDistrict: initialDistrict || '',
        region: initialRegion || '',
        district: initialDistrict || '',
        districtOptions: [],
        init() {
            this.refreshDistricts();
            this.syncDistrictSelection();
        },
        onRegionChange() {
            this.district = '';
            this.savedDistrict = '';
            this.refreshDistricts();
        },
        refreshDistricts() {
            const districts = this.region && this.locations[this.region]
                ? [...this.locations[this.region]]
                : [];

            const preserve = this.savedDistrict || this.district;
            if (preserve && !districts.includes(preserve)) {
                districts.unshift(preserve);
            }

            this.districtOptions = districts;
        },
        syncDistrictSelection() {
            this.$nextTick(() => {
                if (this.savedDistrict) {
                    this.district = this.savedDistrict;
                }
            });
        },
    };
}

export function bindTzAddressGlobally() {
    window.tzAddress = tzAddress;
}
