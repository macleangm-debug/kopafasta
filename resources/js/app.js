import './kf-human-error';
import { bindMoneyFormatGlobally } from './money-format';
import { bindNidaFormatGlobally } from './nida-format';
import { bindTzAddressGlobally } from './tz-address';
import { bindSubmitLoading } from './submit-loading';
import { bindDigitsOnlyGlobally } from './digits-only';
import { bindPageTransitions } from './page-transitions';
import { bindFormDrafts } from './form-draft';
import { bindScreeningChecklistSave } from './screening-checklist-save';
import { bindPaymentContinuation } from './payment-continuation';

bindMoneyFormatGlobally();
bindNidaFormatGlobally();
bindTzAddressGlobally();
bindSubmitLoading();
bindDigitsOnlyGlobally();
bindPageTransitions();
bindFormDrafts();
bindScreeningChecklistSave();
bindPaymentContinuation();

// Never prompt for browser Notification / Push permission (mobile web stays app-clean).
// In-app / SMS / email / Settings Hub notifications are unchanged.
function quietBrowserPermissionPrompts() {
    try {
        if (typeof Notification !== 'undefined' && Notification.requestPermission) {
            Notification.requestPermission = () => Promise.resolve('denied');
        }
    } catch (e) {
        // Ignore — older browsers may freeze Notification.
    }

    try {
        if (typeof PushManager !== 'undefined' && PushManager.prototype && PushManager.prototype.subscribe) {
            PushManager.prototype.subscribe = () => Promise.reject(new DOMException('Browser push is disabled', 'NotAllowedError'));
        }
    } catch (e) {
        // Ignore — Push API is optional.
    }
}

quietBrowserPermissionPrompts();
