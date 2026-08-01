{{-- Global in-page document preview for borrower/partner shells (image + PDF). --}}
<div id="kf-site-doc-lightbox" class="hidden fixed inset-0 z-[90]" aria-hidden="true">
    <div class="absolute inset-0 bg-black/70" data-kf-doc-close></div>
    <div class="relative z-10 flex h-full w-full flex-col items-center justify-center p-4">
        <div class="mb-3 flex w-full max-w-4xl items-center justify-between gap-3 text-white">
            <p id="kf-site-doc-title" class="truncate text-sm font-semibold"></p>
            <button type="button" data-kf-doc-close class="rounded-lg bg-white/10 px-3 py-1.5 text-sm font-semibold hover:bg-white/20">
                {{ __('borrower.profile.cancel') }}
            </button>
        </div>
        <iframe id="kf-site-doc-frame" class="hidden h-[85vh] w-[95vw] max-w-4xl rounded-xl bg-white shadow-2xl" title="Document"></iframe>
        <img id="kf-site-doc-image" alt="" class="hidden max-h-[90vh] max-w-[95vw] rounded-xl object-contain shadow-2xl">
    </div>
</div>

<script>
window.kfSiteOpenDocumentPreview = function (url, title, type) {
    var root = document.getElementById('kf-site-doc-lightbox');
    var frame = document.getElementById('kf-site-doc-frame');
    var image = document.getElementById('kf-site-doc-image');
    var titleEl = document.getElementById('kf-site-doc-title');
    if (!root || !url) return;

    var isPdf = type === 'pdf' || String(url).toLowerCase().indexOf('.pdf') !== -1;
    titleEl.textContent = title || 'Document';

    if (isPdf) {
        image.classList.add('hidden');
        image.removeAttribute('src');
        frame.classList.remove('hidden');
        frame.src = url;
    } else {
        frame.classList.add('hidden');
        frame.removeAttribute('src');
        image.classList.remove('hidden');
        image.src = url;
        image.alt = title || 'Document';
    }

    root.classList.remove('hidden');
    root.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
};

window.kfSiteCloseDocumentPreview = function () {
    var root = document.getElementById('kf-site-doc-lightbox');
    var frame = document.getElementById('kf-site-doc-frame');
    var image = document.getElementById('kf-site-doc-image');
    if (!root) return;
    root.classList.add('hidden');
    root.setAttribute('aria-hidden', 'true');
    if (frame) frame.removeAttribute('src');
    if (image) image.removeAttribute('src');
    document.body.classList.remove('overflow-hidden');
};

document.addEventListener('click', function (event) {
    if (event.target.closest('[data-kf-doc-close]')) {
        window.kfSiteCloseDocumentPreview();
    }
});
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        window.kfSiteCloseDocumentPreview();
    }
});
</script>
