import { bindMoneyFormatGlobally } from './money-format';
import { bindNidaFormatGlobally } from './nida-format';
import { bindTzAddressGlobally } from './tz-address';
import { bindSubmitLoading } from './submit-loading';
import { bindDigitsOnlyGlobally } from './digits-only';
import { bindPageTransitions } from './page-transitions';
import { bindFormDrafts } from './form-draft';

bindMoneyFormatGlobally();
bindNidaFormatGlobally();
bindTzAddressGlobally();
bindSubmitLoading();
bindDigitsOnlyGlobally();
bindPageTransitions();
bindFormDrafts();

// Never prompt for browser Notification / Push permission (mobile web stays app-clean).
try {
    if (typeof Notification !== 'undefined' && Notification.requestPermission) {
        Notification.requestPermission = () => Promise.resolve('denied');
    }
} catch (e) {
    // Ignore — older browsers may freeze Notification.
}
