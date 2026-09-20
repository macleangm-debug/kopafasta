/**
 * User-facing request failures. Never surface HTML, exception pages, or database text.
 */
export function kfHumanErrorMessage(raw, fallback = 'Something went wrong. Please try again.') {
    const text = String(raw || '').trim();
    if (
        text === ''
        || text.startsWith('<')
        || /<!doctype/i.test(text)
        || /SQLSTATE\[|Stack trace:|Whoops, looks like|Illuminate\\/i.test(text)
    ) {
        return fallback;
    }

    return text.length > 180 ? `${text.slice(0, 177)}…` : text;
}

window.kfHumanErrorMessage = kfHumanErrorMessage;
