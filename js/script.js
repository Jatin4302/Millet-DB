const form = document.getElementById('searchForm');
const resultsArea = document.getElementById('resultsArea');
const isFilePreview = window.location.protocol === 'file:';

if (form && resultsArea) {
    const dataset = document.getElementById('dataset');

    function updateDatasetFields() {
        const isSsr = dataset.value === 'ssr';
        document.querySelectorAll('.ssr-filter').forEach((field) => { field.hidden = !isSsr; });
        document.querySelectorAll('.transcriptomics-filter').forEach((field) => { field.hidden = isSsr; });
    }

    function runSearch(page = 1) {
        if (isFilePreview) {
            resultsArea.innerHTML = '<p class="no-results">This search requires a local PHP server. Run <strong>php -S localhost:8000</strong> from the project folder and reload the page.</p>';
            return;
        }

        const params = new URLSearchParams(new FormData(form));
        params.set('page', page);
        resultsArea.innerHTML = '<p class="no-results">Searching the collection...</p>';
        fetch(`php/search.php?${params}`)
            .then((response) => {
                if (!response.ok) throw new Error(`Search failed with status ${response.status}`);
                return response.json();
            })
            .then((data) => renderResults(data, params))
            .catch((error) => {
                resultsArea.innerHTML = '<p class="no-results">Search unavailable. Start the PHP server and try again.</p>';
                console.error(error);
            });
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        runSearch();
    });

    dataset.addEventListener('change', updateDatasetFields);
    updateDatasetFields();

    document.querySelectorAll('[data-query]').forEach((button) => {
        button.addEventListener('click', () => {
            document.getElementById('species').value = button.dataset.query === 'millet' ? 'Eleusine coracana' : '';
            document.getElementById('gene_id').value = button.dataset.query === 'gene' ? 'OsGene001' : '';
            runSearch();
        });
    });

    resultsArea.addEventListener('click', (event) => {
        const pageButton = event.target.closest('[data-page]');
        if (pageButton) runSearch(Number(pageButton.dataset.page));
    });
}

document.querySelectorAll('[data-dataset]').forEach((link) => {
    link.addEventListener('click', () => {
        const dataset = document.getElementById('dataset');
        if (dataset) dataset.value = link.dataset.dataset;
    });
});

const revealCards = document.querySelectorAll('.reveal-card');
if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const revealObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.15 });

    revealCards.forEach((card) => revealObserver.observe(card));
} else {
    revealCards.forEach((card) => card.classList.add('is-visible'));
}

const heroSlideshow = document.querySelector('[data-hero-slideshow]');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const animatedHeader = document.querySelector('.site-header');
let previousScrollPosition = window.scrollY;

function updateHeaderScrollState() {
    if (!animatedHeader) return;
    const currentScrollPosition = Math.max(window.scrollY, 0);
    const isScrollingDown = currentScrollPosition > previousScrollPosition;
    animatedHeader.classList.toggle('is-scrolled', currentScrollPosition > 24);

    if (currentScrollPosition <= 24 || !isScrollingDown) {
        animatedHeader.classList.remove('is-hidden');
    } else if (currentScrollPosition > 56) {
        animatedHeader.classList.add('is-hidden');
    }

    previousScrollPosition = currentScrollPosition;
}

if (animatedHeader) {
    updateHeaderScrollState();
    window.addEventListener('scroll', updateHeaderScrollState, { passive: true });
}

function applyManagedImages(config) {
    const images = config?.images || {};
    const root = document.documentElement;
    const defaultPageImage = 'assets/page-bg.svg';
    const navbarBackground = images.navbar_background;
    const defaultHeroImages = ['assets/hero-1.svg', 'assets/hero-2.svg', 'assets/hero-3.svg'];
    const pageBackground = images.body_background;
    const resolveImageUrl = (path) => new URL(path, document.baseURI).href;
    if (navbarBackground?.path) {
        root.style.setProperty('--managed-navbar-image', `url("${resolveImageUrl(navbarBackground.path)}")`);
    } else {
        root.style.setProperty('--managed-navbar-image', `url("${defaultPageImage}")`);
    }
    if (pageBackground?.path) {
        const pageImageUrl = resolveImageUrl(pageBackground.path);
        document.documentElement.style.backgroundImage = `linear-gradient(rgba(18,53,46,.35), rgba(18,53,46,.35)), url("${pageImageUrl}")`;
        root.style.setProperty('--managed-page-image', `url("${pageImageUrl}")`);
        document.body.classList.add('has-managed-page-image');
    } else {
        root.style.setProperty('--managed-page-image', `url("${defaultPageImage}")`);
        document.body.classList.add('has-managed-page-image');
    }
    document.querySelectorAll('[data-hero-slide]').forEach((image, index) => {
        const managed = images[`hero_${index + 1}`];
        if (managed?.path) {
            image.src = managed.path;
            image.alt = managed.alt || image.alt;
        } else if (defaultHeroImages[index]) {
            image.src = defaultHeroImages[index];
            image.alt = image.alt || 'Millet genomic resource image';
        }
    });
    document.querySelectorAll('[data-resource-image]').forEach((element) => {
        const managed = images[element.dataset.resourceImage];
        if (managed?.path) {
            const imageUrl = resolveImageUrl(managed.path);
            element.style.backgroundImage = `linear-gradient(180deg, transparent 40%, rgba(18, 53, 46, .32)), url("${imageUrl}")`;
            element.style.backgroundSize = 'cover';
            element.style.backgroundPosition = 'center';
            element.setAttribute('aria-label', managed.alt || element.getAttribute('aria-label'));
        }
    });

    document.querySelectorAll('[data-content]').forEach((element) => {
        const value = config?.settings?.[element.dataset.content];
        if (value) element.textContent = value;
    });

    const settings = config?.settings || {};
    const palettes = {
        botanical: { ink: '#173b32', deep: '#12352e', muted: '#688078', line: '#d7e2dc', cream: '#f5f5ec', lime: '#d8e95a', orange: '#f18b4e' },
        terracotta: { ink: '#3e3028', deep: '#4c3b31', muted: '#7b7166', line: '#ded2c5', cream: '#f5efe8', lime: '#d6c46b', orange: '#c96b46' },
        ink: { ink: '#24364b', deep: '#1f3348', muted: '#718095', line: '#d8e0e8', cream: '#f3f6f8', lime: '#d8d36c', orange: '#c9824c' }
    };
    const fonts = {
        manrope: { body: "'Manrope', sans-serif", heading: "'Libre Baskerville', Georgia, serif" },
        baskerville: { body: "'Libre Baskerville', Georgia, serif", heading: "'Libre Baskerville', Georgia, serif" },
        mono: { body: "'DM Mono', monospace", heading: "'DM Mono', monospace" }
    };
    const palette = settings.palette === 'custom' ? {
        ink: settings.palette_ink || palettes.botanical.ink,
        deep: settings.palette_deep || palettes.botanical.deep,
        muted: settings.palette_muted || palettes.botanical.muted,
        line: settings.palette_line || palettes.botanical.line,
        cream: settings.palette_cream || palettes.botanical.cream,
        lime: settings.palette_lime || palettes.botanical.lime,
        orange: settings.palette_orange || palettes.botanical.orange
    } : (palettes[settings.palette] || palettes.botanical);
    const font = fonts[settings.font_family] || fonts.manrope;
    Object.entries(palette).forEach(([name, value]) => root.style.setProperty(`--${name}`, value));
    root.style.setProperty('--body-font', font.body);
    root.style.setProperty('--heading-font', font.heading);
    const savedFontScale = localStorage.getItem('millet-font-scale');
    if (!savedFontScale && settings.font_scale) {
        document.body.classList.remove('font-compact', 'font-small', 'font-normal', 'font-large');
        document.body.classList.add(`font-${settings.font_scale}`);
    }
}

if (!isFilePreview) {
    fetch('php/site_config.php')
        .then((response) => response.ok ? response.json() : null)
        .then(applyManagedImages)
        .catch(() => undefined);
}

if (heroSlideshow && !reducedMotion) {
    const slides = [...heroSlideshow.querySelectorAll('[data-hero-slide]')];
    let activeIndex = 0;
    window.setInterval(() => {
        slides[activeIndex].classList.remove('is-active');
        activeIndex = (activeIndex + 1) % slides.length;
        slides[activeIndex].classList.add('is-active');
    }, 5200);
}

const toolTabs = document.querySelectorAll('[data-tool-target]');
const toolTitle = document.querySelector('[data-tool-title]');
const toolDescription = document.querySelector('[data-tool-description]');
const toolShortTitle = document.querySelector('[data-tool-short-title]');
const toolFrame = document.querySelector('[data-tool-frame]');
const toolOpenLinks = document.querySelectorAll('[data-tool-open]');
const tools = {
    blast: {
        title: 'NCBI BLAST', shortTitle: 'BLAST',
        description: 'Compare nucleotide or protein sequences against curated NCBI databases.',
        url: 'https://blast.ncbi.nlm.nih.gov/Blast.cgi'
    },
    jbrowse: {
        title: 'JBrowse', shortTitle: 'JBROWSE',
        description: 'Explore genome assemblies, annotations, and comparative genomic tracks.',
        url: 'https://jbrowse.org/jb2/'
    },
    phytosome: {
        title: 'Phytosome', shortTitle: 'PHYTOSOME',
        description: 'Browse plant genome resources, gene models, and comparative annotations.',
        url: 'https://phytozome-next.jgi.doe.gov/'
    },
    pfam: {
        title: 'Pfam protein families', shortTitle: 'PFAM',
        description: 'Explore conserved protein domains and family annotations through InterPro.',
        url: 'https://www.ebi.ac.uk/interpro/entry/pfam/'
    },
    'np-conversion': {
        title: 'N-P conversion tool', shortTitle: 'N-P CONVERSION',
        description: 'Convert nitrogen and protein values for crop and nutritional analyses.',
        url: 'https://www.fao.org/3/y1579e/y1579e06.htm'
    },
    'wolf-psort': {
        title: 'Wolf PSORT', shortTitle: 'WOLF PSORT',
        description: 'Predict subcellular localization from protein sequence characteristics.',
        url: 'https://wolfpsort.hgc.jp/'
    },
    chopchop: {
        title: 'ChopChop guide design', shortTitle: 'CHOPCHOP',
        description: 'Design CRISPR guides and inspect candidate targets for plant genomes.',
        url: 'https://chopchop.cbu.uib.no/'
    },
    epcr: {
        title: 'NCBI ePCR', shortTitle: 'ePCR',
        description: 'Check primer pairs against sequence databases for expected amplification.',
        url: 'https://www.ncbi.nlm.nih.gov/sutils/e-pcr/'
    },
    misa: {
        title: 'MISA', shortTitle: 'MISA',
        description: 'Identify microsatellites and compound microsatellite motifs in sequences.',
        url: 'https://webblast.ipk-gatersleben.de/misa/'
    },
    kegg: {
        title: 'KEGG', shortTitle: 'KEGG',
        description: 'Inspect genes, pathways, and biological systems across organisms.',
        url: 'https://www.genome.jp/kegg/'
    },
    mapman: {
        title: 'MapMan', shortTitle: 'MAPMAN',
        description: 'Visualize and interpret gene expression data in biological pathway maps.',
        url: 'https://mapman.gabipd.org/'
    }
};

function selectTool(toolKey) {
    const tool = tools[toolKey];
    if (!tool || !toolTitle || !toolFrame) return;
    toolTabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.toolTarget === toolKey));
    toolTitle.textContent = tool.title;
    toolDescription.textContent = tool.description;
    toolShortTitle.textContent = tool.shortTitle;
    toolFrame.src = tool.url;
    toolOpenLinks.forEach((link) => { link.href = tool.url; });
}

toolTabs.forEach((tab) => tab.addEventListener('click', () => {
    const toolKey = tab.dataset.toolTarget;
    selectTool(toolKey);
    history.replaceState(null, '', `#tool-${toolKey}`);
}));

function selectToolFromHash() {
    const toolKey = window.location.hash.replace(/^#tool-/, '');
    if (tools[toolKey]) selectTool(toolKey);
}

selectToolFromHash();
window.addEventListener('hashchange', selectToolFromHash);

const fontButtons = document.querySelectorAll('[data-font-size]');
const fontSizes = { small: 'font-small', normal: 'font-normal', large: 'font-large' };

function applyFontScale(level) {
    const className = fontSizes[level] || fontSizes.normal;
    document.body.classList.remove('font-small', 'font-normal', 'font-large');
    document.body.classList.add(className);
    localStorage.setItem('millet-font-scale', level in fontSizes ? level : 'normal');
    fontButtons.forEach((control) => {
        const isSelected = control.dataset.fontSize === (level in fontSizes ? level : 'normal');
        control.classList.toggle('is-active', isSelected);
        control.setAttribute('aria-pressed', String(isSelected));
    });
}

applyFontScale(localStorage.getItem('millet-font-scale') || 'normal');

fontButtons.forEach((button) => {
    button.addEventListener('click', () => {
        applyFontScale(button.dataset.fontSize);
    });
});

const toolkitFloat = document.querySelector('.toolkit-float');
const toolkitToggle = document.querySelector('.toolkit-toggle');

const siteHeader = document.querySelector('.site-header');
const siteNavRow = siteHeader?.querySelector('.nav-row');

if (siteHeader && siteNavRow) {
    let navCollapseTimer;

    const openSiteNav = () => {
        window.clearTimeout(navCollapseTimer);
        siteHeader.classList.add('is-nav-open');
    };

    const collapseSiteNav = () => {
        window.clearTimeout(navCollapseTimer);
        navCollapseTimer = window.setTimeout(() => siteHeader.classList.remove('is-nav-open'), 1000);
    };

    siteHeader.addEventListener('pointerenter', openSiteNav);
    siteHeader.addEventListener('pointerleave', collapseSiteNav);
    siteHeader.addEventListener('focusin', openSiteNav);
    siteHeader.addEventListener('focusout', (event) => {
        if (!siteHeader.contains(event.relatedTarget)) collapseSiteNav();
    });

    document.addEventListener('pointermove', (event) => {
        if (!siteHeader.contains(event.target)) collapseSiteNav();
    }, { passive: true });
}

if (toolkitFloat && toolkitToggle) {
    let toolkitCollapseTimer;

    const openToolkit = () => {
        window.clearTimeout(toolkitCollapseTimer);
        toolkitFloat.classList.add('is-open');
        toolkitToggle.setAttribute('aria-expanded', 'true');
    };

    const collapseToolkit = (delay = 0) => {
        window.clearTimeout(toolkitCollapseTimer);
        toolkitCollapseTimer = window.setTimeout(() => {
            toolkitFloat.classList.remove('is-open');
            toolkitToggle.setAttribute('aria-expanded', 'false');
        }, delay);
    };

    toolkitToggle.addEventListener('click', () => {
        const isOpen = toolkitFloat.classList.toggle('is-open');
        toolkitToggle.setAttribute('aria-expanded', String(isOpen));
    });

}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' }[character]));
}

function renderResults(data, params) {
    if (!data.rows || data.rows.length === 0) {
        resultsArea.innerHTML = '<p class="no-results">No matching records found. Try a broader search.</p>';
        return;
    }
    const columns = Object.keys(data.rows[0]);
    const headers = columns.map((column) => `<th>${escapeHtml(column.replaceAll('_', ' '))}</th>`).join('');
    const rows = data.rows.map((row) => `<tr>${columns.map((column) => `<td>${escapeHtml(row[column])}</td>`).join('')}</tr>`).join('');
    const previousPage = data.page > 1 ? `<button type="button" data-page="${data.page - 1}">← Previous</button>` : '';
    const nextPage = data.page < data.pages ? `<button type="button" data-page="${data.page + 1}">Next →</button>` : '';
    resultsArea.innerHTML = `<div class="results-wrap"><div class="results-meta"><p>${data.count} record${data.count === 1 ? '' : 's'} found · page ${data.page} of ${data.pages}</p><a class="download-link" href="php/export.php?${params}" download><img class="ui-icon" src="assets/Icons/icons8-download-24.png" alt="" aria-hidden="true">Download CSV ↓</a></div><table class="results-table"><thead><tr>${headers}</tr></thead><tbody>${rows}</tbody></table><div class="pagination" aria-label="Search results pages">${previousPage}${nextPage}</div></div>`;
}