export function bindReceiptSave() {
    document.addEventListener('click', (event) => {
        const save = event.target.closest('[data-kf-save-receipt]');
        if (save instanceof HTMLElement) {
            event.preventDefault();
            captureReceipt(save.getAttribute('data-kf-save-receipt')).then((blob) => {
                if (! blob) {
                    return;
                }
                const root = receiptRoot(save.getAttribute('data-kf-save-receipt'));
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = root?.dataset.filename || 'kopafasta-receipt.png';
                link.click();
                window.setTimeout(() => URL.revokeObjectURL(link.href), 1500);
            }).catch(() => {});
            return;
        }

        const share = event.target.closest('[data-kf-share-receipt]');
        if (share instanceof HTMLElement) {
            event.preventDefault();
            captureReceipt(share.getAttribute('data-kf-share-receipt')).then(async (blob) => {
                if (! blob) {
                    return;
                }
                const root = receiptRoot(share.getAttribute('data-kf-share-receipt'));
                const file = new File([blob], root?.dataset.filename || 'kopafasta-receipt.png', { type: 'image/png' });
                if (navigator.share && navigator.canShare?.({ files: [file] })) {
                    await navigator.share({ files: [file], title: root?.dataset.brand || 'kopafasta' });
                    return;
                }
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = file.name;
                link.click();
                window.setTimeout(() => URL.revokeObjectURL(link.href), 1500);
            }).catch(() => {});
        }
    });
}

function receiptRoot(selector) {
    const node = document.querySelector(selector || '[data-kf-receipt]');
    return node instanceof HTMLElement ? node : null;
}

function captureReceipt(selector) {
    const root = receiptRoot(selector);
    if (! root) {
        return Promise.reject(new Error('receipt'));
    }

    return snapshotReceipt(root).catch(() => paintReceipt(root));
}

function snapshotReceipt(root) {
    const width = Math.max(360, Math.ceil(root.scrollWidth || root.offsetWidth || 360));
    const height = Math.max(480, Math.ceil(root.scrollHeight || root.offsetHeight || 480));
    const clone = root.cloneNode(true);
    if (! (clone instanceof HTMLElement)) {
        return Promise.reject(new Error('clone'));
    }
    clone.style.width = `${width}px`;
    clone.style.background = '#ffffff';
    const svg = `<?xml version="1.0" encoding="UTF-8"?>`
        + `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}">`
        + `<foreignObject width="100%" height="100%">`
        + `<div xmlns="http://www.w3.org/1999/xhtml">${clone.outerHTML}</div>`
        + `</foreignObject></svg>`;
    const blob = new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(blob);

    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = width * 2;
            canvas.height = height * 2;
            const ctx = canvas.getContext('2d');
            if (! ctx) {
                URL.revokeObjectURL(url);
                reject(new Error('canvas'));
                return;
            }
            ctx.scale(2, 2);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, width, height);
            ctx.drawImage(image, 0, 0, width, height);
            URL.revokeObjectURL(url);
            canvas.toBlob((png) => (png ? resolve(png) : reject(new Error('blob'))), 'image/png');
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('svg'));
        };
        image.src = url;
    });
}

function paintReceipt(root) {
    const data = root.dataset;
    let rows = [];
    try {
        rows = JSON.parse(data.rows || '[]');
    } catch (e) {
        rows = [];
    }
    if (! Array.isArray(rows) || rows.length === 0) {
        rows = [
            { label: data.typeLabel || 'Type', value: data.type || '' },
            { label: data.referenceLabel || 'Reference', value: data.reference || '' },
            { label: data.dateLabel || 'Date', value: data.date || '' },
            { label: data.statusLabel || 'Status', value: data.status || '' },
        ];
        if (data.phone) {
            rows.push({ label: data.phoneLabel || 'Mobile', value: data.phone });
        }
    }

    const width = 720;
    const height = 280 + (rows.length * 72) + (data.footer ? 70 : 0) + (data.keep ? 50 : 0);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (! ctx) {
        return Promise.reject(new Error('canvas'));
    }

    const paint = (logo) => {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, width, height);
        ctx.strokeStyle = '#e5e7eb';
        ctx.lineWidth = 2;
        ctx.strokeRect(24, 24, width - 48, height - 48);

        if (logo) {
            ctx.drawImage(logo, 56, 56, 52, 52);
        }

        ctx.fillStyle = '#111827';
        ctx.font = '700 30px ui-sans-serif, system-ui, sans-serif';
        ctx.fillText(data.brand || 'kopafasta', logo ? 124 : 56, 92);
        ctx.fillStyle = '#6b7280';
        ctx.font = '600 13px ui-sans-serif, system-ui, sans-serif';
        ctx.fillText(String(data.kicker || 'RECEIPT').toUpperCase(), 56, 150);

        ctx.fillStyle = '#111827';
        ctx.font = '800 44px ui-sans-serif, system-ui, sans-serif';
        ctx.fillText(data.amount || '', 56, 230);

        let y = 300;
        rows.forEach((row) => {
            ctx.fillStyle = '#6b7280';
            ctx.font = '600 13px ui-sans-serif, system-ui, sans-serif';
            ctx.fillText(String(row.label || '').toUpperCase(), 56, y);
            ctx.fillStyle = '#111827';
            ctx.font = '700 20px ui-sans-serif, system-ui, sans-serif';
            ctx.fillText(String(row.value || ''), 56, y + 28);
            y += 72;
        });

        if (data.footer) {
            ctx.fillStyle = '#4b5563';
            ctx.font = '500 14px ui-sans-serif, system-ui, sans-serif';
            wrapText(ctx, String(data.footer), 56, y, width - 112, 20);
            y += 48;
        }
        if (data.keep) {
            ctx.fillStyle = '#111827';
            ctx.font = '600 14px ui-sans-serif, system-ui, sans-serif';
            ctx.fillText(String(data.keep), 56, y);
        }
    };

    return new Promise((resolve, reject) => {
        const finish = (logo) => {
            paint(logo);
            canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('blob'))), 'image/png');
        };
        const mark = new Image();
        mark.crossOrigin = 'anonymous';
        mark.onload = () => finish(mark);
        mark.onerror = () => finish(null);
        mark.src = data.mark || '/images/brand/kopafasta-mark.png';
    });
}

function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
    const words = text.split(' ');
    let line = '';
    let row = y;
    words.forEach((word) => {
        const test = line ? `${line} ${word}` : word;
        if (ctx.measureText(test).width > maxWidth && line) {
            ctx.fillText(line, x, row);
            line = word;
            row += lineHeight;
        } else {
            line = test;
        }
    });
    if (line) {
        ctx.fillText(line, x, row);
    }
}
