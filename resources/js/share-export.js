import { toBlob } from 'html-to-image';

/**
 * Export a DOM node as a PNG File (canonical card / report fragment — not a page screenshot).
 */
export async function exportElementPngFile(el, filename = 'kopafasta.png') {
    if (! el) return null;
    const blob = await toBlob(el, {
        pixelRatio: 2,
        cacheBust: true,
        backgroundColor: null,
    });
    if (! blob) return null;

    return new File([blob], filename, { type: 'image/png' });
}

export function bindShareExport() {
    window.kfExportElementPngFile = exportElementPngFile;
}
