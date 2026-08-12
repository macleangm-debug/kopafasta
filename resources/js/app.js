import { bindMoneyFormatGlobally } from './money-format';
import { bindNidaFormatGlobally } from './nida-format';
import { bindTzAddressGlobally } from './tz-address';
import { bindSubmitLoading } from './submit-loading';
import { bindDigitsOnlyGlobally } from './digits-only';

bindMoneyFormatGlobally();
bindNidaFormatGlobally();
bindTzAddressGlobally();
bindSubmitLoading();
bindDigitsOnlyGlobally();

// Never prompt for browser Notification / Push permission (mobile web stays app-clean).
try {
    if (typeof Notification !== 'undefined' && Notification.requestPermission) {
        Notification.requestPermission = () => Promise.resolve('denied');
    }
} catch (e) {
    // Ignore — older browsers may freeze Notification.
}
