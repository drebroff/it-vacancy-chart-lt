// CVbankas IT Trends Dashboard Logic

const PALETTE = {
    python: { border: '#e11d48', bg: 'rgba(225, 29, 72, 0.15)' },
    javascript: { border: '#10b981', bg: 'rgba(16, 185, 129, 0.15)' },
    php: { border: '#eab308', bg: 'rgba(234, 179, 8, 0.15)' },
    java: { border: '#3b82f6', bg: 'rgba(59, 130, 246, 0.15)' },
    dotnet: { border: '#f97316', bg: 'rgba(249, 115, 22, 0.15)' },
    cpp: { border: '#84cc16', bg: 'rgba(132, 204, 22, 0.15)' },
    go: { border: '#a855f7', bg: 'rgba(168, 85, 247, 0.15)' },
    rust: { border: '#b45309', bg: 'rgba(180, 83, 9, 0.15)' },
    ruby: { border: '#f43f5e', bg: 'rgba(244, 63, 94, 0.15)' },
    android: { border: '#06b6d4', bg: 'rgba(6, 182, 212, 0.15)' },
    ios: { border: '#ec4899', bg: 'rgba(236, 72, 153, 0.15)' },
    sap: { border: '#d97706', bg: 'rgba(217, 119, 6, 0.15)' },
    salesforce: { border: '#0284c7', bg: 'rgba(2, 132, 199, 0.15)' },
    dynamics: { border: '#059669', bg: 'rgba(5, 150, 105, 0.15)' },
    servicenow: { border: '#2dd4bf', bg: 'rgba(45, 212, 191, 0.15)' },
    pm: { border: '#0d9488', bg: 'rgba(13, 148, 136, 0.15)' },
    qa: { border: '#c084fc', bg: 'rgba(192, 132, 252, 0.15)' },
    devops: { border: '#f472b6', bg: 'rgba(244, 114, 182, 0.15)' },
    ai: { border: '#facc15', bg: 'rgba(250, 204, 21, 0.15)' },
    analyst: { border: '#6366f1', bg: 'rgba(99, 102, 241, 0.15)' },
    helpdesk: { border: '#64748b', bg: 'rgba(100, 116, 139, 0.15)' },
};

let rawData = null;
let othersData = [];
let filteredOthers = [];
let othersCurrentPage = 1;
const OTHERS_PAGE_SIZE = 25;
let chartInstance = null;
let activeGroup = 'all';
let activeRange = 'all';

document.addEventListener('DOMContentLoaded', async () => {
    initTheme();
    setupModal();
    setupControls();
    setupLegendToggle();
    await loadData();
});

// Theme Management
function initTheme() {
    const savedTheme = localStorage.getItem('cvbankas_theme') || 'dark';
    setTheme(savedTheme);

    document.getElementById('theme-toggle').addEventListener('click', () => {
        const isDark = document.body.classList.contains('dark-theme');
        setTheme(isDark ? 'light' : 'dark');
    });
}

function setTheme(theme) {
    if (theme === 'light') {
        document.body.classList.remove('dark-theme');
        document.body.classList.add('light-theme');
        document.getElementById('theme-icon').textContent = '🌙';
        localStorage.setItem('cvbankas_theme', 'light');
    } else {
        document.body.classList.remove('light-theme');
        document.body.classList.add('dark-theme');
        document.getElementById('theme-icon').textContent = '☀️';
        localStorage.setItem('cvbankas_theme', 'dark');
    }
    if (chartInstance) {
        updateChartTheme();
    }
}

// Data Fetching
async function loadData() {
    try {
        const [histResponse, othersResponse] = await Promise.all([
            fetch('data/history.json?t=' + Date.now()),
            fetch('data/others.json?t=' + Date.now()).catch(() => null)
        ]);

        if (!histResponse.ok) {
            throw new Error('history.json not found. Run collector first.');
        }
        rawData = await histResponse.json();

        if (othersResponse && othersResponse.ok) {
            othersData = await othersResponse.json();
        } else {
            othersData = [];
        }

        // Check if only 1 day is present
        const banner = document.getElementById('timeline-banner');
        if (banner) {
            banner.style.display = rawData.dates.length <= 1 ? 'flex' : 'none';
        }

        renderKPIs();
        renderLegend();
        renderChart();
        initOthersTable();

        const latestDate = rawData.dates[rawData.dates.length - 1] || 'Today';
        document.getElementById('last-updated-label').textContent = `${latestDate} (${rawData.totals[rawData.totals.length - 1] || 0})`;
    } catch (error) {
        console.error('Failed to load chart data:', error);
        document.getElementById('last-updated-label').textContent = 'Waiting for crawl';
    }
}

const BASELINE_DATE = '2026-09-20';

function calculateTrends(dates, totals) {
    if (!dates || dates.length === 0 || !totals || totals.length === 0) {
        return {
            daily: { percent: 0, str: '0%', status: 'neutral' },
            total: { percent: 0, str: '0%', status: 'neutral' }
        };
    }

    const latestIdx = dates.length - 1;
    const latestDate = dates[latestIdx];
    const latestTotal = totals[latestIdx];

    // 1. Daily Trend
    let daily = { percent: 0, str: '0%', status: 'neutral' };
    if (latestDate > BASELINE_DATE && latestIdx > 0) {
        const prevTotal = totals[latestIdx - 1];
        if (prevTotal > 0) {
            const diff = latestTotal - prevTotal;
            const pct = (diff / prevTotal) * 100;
            const sign = pct > 0 ? '+' : '';
            daily = {
                percent: pct,
                str: `${sign}${pct.toFixed(1)}%`,
                status: pct > 0 ? 'up' : (pct < 0 ? 'down' : 'neutral')
            };
        }
    }

    // 2. Total Trend since BASELINE_DATE
    let total = { percent: 0, str: '0%', status: 'neutral' };
    if (latestDate > BASELINE_DATE) {
        const baselineIdx = dates.indexOf(BASELINE_DATE);
        const baseTotal = baselineIdx !== -1 ? totals[baselineIdx] : totals[0];
        if (baseTotal > 0) {
            const diff = latestTotal - baseTotal;
            const pct = (diff / baseTotal) * 100;
            const sign = pct > 0 ? '+' : '';
            total = {
                percent: pct,
                str: `${sign}${pct.toFixed(1)}%`,
                status: pct > 0 ? 'up' : (pct < 0 ? 'down' : 'neutral')
            };
        }
    }

    return { daily, total };
}

// Render Top KPIs
function renderKPIs() {
    if (!rawData || !rawData.totals.length) return;

    const latestTotal = rawData.totals[rawData.totals.length - 1];
    document.getElementById('kpi-total').textContent = latestTotal.toLocaleString();

    // Trends calculation
    const trends = calculateTrends(rawData.dates, rawData.totals);

    const kpiTrend = document.getElementById('kpi-total-trend');
    if (kpiTrend) {
        kpiTrend.textContent = trends.daily.str;
        kpiTrend.className = `trend-badge trend-${trends.daily.status}`;
        kpiTrend.title = `Daily change vs yesterday: ${trends.daily.str}`;
    }

    const headerTrend = document.getElementById('header-total-trend');
    if (headerTrend) {
        headerTrend.textContent = trends.total.str;
        headerTrend.className = `trend-badge trend-${trends.total.status}`;
        headerTrend.title = `Total change since ${BASELINE_DATE}: ${trends.total.str}`;
    }

    const lastIdx = rawData.dates.length - 1;
    let topStack = { id: '', count: -1 };
    let topRole = { id: '', count: -1 };
    let topEnterprise = { id: '', count: -1 };

    for (const [id, def] of Object.entries(rawData.categories)) {
        const count = rawData.series[id]?.[lastIdx] || 0;
        if (def.group === 'stack' && count > topStack.count) {
            topStack = { id: def.label, count };
        } else if (def.group === 'role' && count > topRole.count) {
            topRole = { id: id, count };
        } else if (def.group === 'enterprise' && count > topEnterprise.count) {
            topEnterprise = { id: def.label, count };
        }
    }

    if (topStack.id) {
        document.getElementById('kpi-top-stack').textContent = topStack.id.split('/')[0].trim();
        document.getElementById('kpi-top-stack-sub').textContent = `${topStack.count} active ads`;
    }
    if (topRole.id) {
        const roleMap = {
            pm: 'PM',
            qa: 'QA',
            devops: 'DevOps',
            ai: 'AI',
            analyst: 'Analyst',
            helpdesk: 'Helpdesk'
        };
        const roleElem = document.getElementById('kpi-top-role');
        roleElem.textContent = roleMap[topRole.id] || topRole.id.toUpperCase();
        if (rawData.categories[topRole.id]) {
            roleElem.title = rawData.categories[topRole.id].label;
        }
        document.getElementById('kpi-top-role-sub').textContent = `${topRole.count} active ads`;
    }
    if (topEnterprise.id) {
        document.getElementById('kpi-top-enterprise').textContent = topEnterprise.id.split('/')[0].trim();
        document.getElementById('kpi-top-enterprise-sub').textContent = `${topEnterprise.count} active ads`;
    }
}

// Render Category Chips
function renderLegend() {
    const container = document.getElementById('category-legend');
    container.innerHTML = '';

    const lastIdx = rawData.dates.length - 1;

    for (const [id, def] of Object.entries(rawData.categories)) {
        const count = rawData.series[id]?.[lastIdx] || 0;
        const color = PALETTE[id]?.border || '#94a3b8';

        const chip = document.createElement('div');
        chip.className = 'legend-chip';
        chip.dataset.categoryId = id;
        chip.dataset.group = def.group;
        chip.innerHTML = `
            <span class="chip-color-box" style="background-color: ${color}"></span>
            <span class="chip-label">${def.label}</span>
            <span class="chip-count">${count}</span>
        `;

        chip.addEventListener('click', () => {
            const isHidden = chip.classList.toggle('disabled');
            toggleDatasetVisibility(id, !isHidden);
        });

        container.appendChild(chip);
    }
    filterLegendByGroup();
}

function filterLegendByGroup() {
    const chips = document.querySelectorAll('.legend-chip');
    let visibleCount = 0;

    chips.forEach(chip => {
        const chipGroup = chip.dataset.group;
        const shouldShow = activeGroup === 'all' || chipGroup === activeGroup;
        chip.style.display = shouldShow ? 'inline-flex' : 'none';
        if (shouldShow) visibleCount++;

        const isExcluded = chip.classList.contains('disabled');
        toggleDatasetVisibility(chip.dataset.categoryId, shouldShow && !isExcluded);
    });

    const counter = document.getElementById('active-category-count');
    if (counter) counter.textContent = visibleCount;
}

function toggleDatasetVisibility(categoryId, visible) {
    if (!chartInstance) return;
    const dataset = chartInstance.data.datasets.find(ds => ds.id === categoryId);
    if (dataset) {
        dataset.hidden = !visible;
        chartInstance.update('none');
    }
}

function setupLegendToggle() {
    const toggleBtn = document.getElementById('btn-toggle-legend');
    const legend = document.getElementById('category-legend');

    if (!toggleBtn || !legend) return;

    toggleBtn.addEventListener('click', () => {
        const isCollapsed = legend.classList.toggle('collapsed');
        toggleBtn.textContent = isCollapsed ? 'Show All ▼' : 'Collapse ▲';
    });
}

// Chart Initialization
function renderChart() {
    const ctx = document.getElementById('vacancies-chart').getContext('2d');
    const isDark = document.body.classList.contains('dark-theme');
    const isSingleDay = rawData.dates.length <= 1;

    const datasets = Object.entries(rawData.categories).map(([id, def]) => {
        const color = PALETTE[id] || { border: '#94a3b8', bg: 'rgba(148, 163, 184, 0.1)' };
        const dataArr = rawData.series[id] || [];
        const hasVacancies = dataArr.some(v => v > 0);

        return {
            id,
            label: def.label,
            group: def.group,
            data: dataArr,
            borderColor: color.border,
            backgroundColor: color.bg,
            borderWidth: 2.2,
            tension: 0.25,
            // If single day, make points bigger so they stand out clearly
            pointRadius: isSingleDay ? 6 : (rawData.dates.length > 30 ? 1 : 4),
            pointHoverRadius: isSingleDay ? 9 : 6,
            pointHitRadius: 15, // Easy finger touch on mobile
            pointBackgroundColor: color.border,
            pointBorderColor: isDark ? '#162036' : '#ffffff',
            pointBorderWidth: 2,
            // If category has 0 vacancies on single-day, optionally hide or show
            hidden: false
        };
    });

    const config = {
        type: 'line',
        data: {
            labels: rawData.dates,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: isSingleDay ? 'nearest' : 'index',
                intersect: false
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark ? '#1e293b' : '#ffffff',
                    titleColor: isDark ? '#f8fafc' : '#0f172a',
                    bodyColor: isDark ? '#cbd5e1' : '#334155',
                    borderColor: isDark ? '#334155' : '#e2e8f0',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 5,
                    usePointStyle: true,
                    callbacks: {
                        label: function (context) {
                            return ` ${context.dataset.label}: ${context.parsed.y} vacancies`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    offset: isSingleDay, // Centers single point neatly
                    grid: {
                        color: isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        color: isDark ? '#94a3b8' : '#64748b',
                        maxTicksLimit: 8,
                        font: {
                            size: 11
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: isSingleDay ? 35 : undefined,
                    grid: {
                        color: isDark ? 'rgba(255, 255, 255, 0.07)' : 'rgba(0, 0, 0, 0.07)'
                    },
                    ticks: {
                        color: isDark ? '#94a3b8' : '#64748b',
                        font: {
                            size: 11
                        },
                        precision: 0
                    },
                    title: {
                        display: window.innerWidth > 480,
                        text: 'Vacancies',
                        color: isDark ? '#94a3b8' : '#64748b'
                    }
                }
            }
        }
    };

    if (chartInstance) {
        chartInstance.destroy();
    }
    chartInstance = new Chart(ctx, config);
    applyTimeRange(activeRange);
}

function updateChartTheme() {
    if (!chartInstance) return;
    const isDark = document.body.classList.contains('dark-theme');

    chartInstance.options.scales.x.grid.color = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';
    chartInstance.options.scales.y.grid.color = isDark ? 'rgba(255, 255, 255, 0.07)' : 'rgba(0, 0, 0, 0.07)';
    chartInstance.options.scales.x.ticks.color = isDark ? '#94a3b8' : '#64748b';
    chartInstance.options.scales.y.ticks.color = isDark ? '#94a3b8' : '#64748b';
    if (chartInstance.options.scales.y.title) {
        chartInstance.options.scales.y.title.color = isDark ? '#94a3b8' : '#64748b';
    }
    chartInstance.options.plugins.tooltip.backgroundColor = isDark ? '#1e293b' : '#ffffff';
    chartInstance.options.plugins.tooltip.titleColor = isDark ? '#f8fafc' : '#0f172a';
    chartInstance.options.plugins.tooltip.bodyColor = isDark ? '#cbd5e1' : '#334155';
    chartInstance.options.plugins.tooltip.borderColor = isDark ? '#334155' : '#e2e8f0';

    chartInstance.data.datasets.forEach(ds => {
        ds.pointBorderColor = isDark ? '#162036' : '#ffffff';
    });

    chartInstance.update();
}

function applyTimeRange(range) {
    if (!chartInstance || !rawData) return;
    activeRange = range;

    const totalDays = rawData.dates.length;
    let sliceDays = totalDays;

    if (range !== 'all') {
        sliceDays = Math.min(totalDays, parseInt(range, 10));
    }

    const startIdx = totalDays - sliceDays;
    const filteredLabels = rawData.dates.slice(startIdx);

    chartInstance.data.labels = filteredLabels;
    chartInstance.data.datasets.forEach(ds => {
        const fullSeries = rawData.series[ds.id] || [];
        ds.data = fullSeries.slice(startIdx);
        ds.pointRadius = totalDays <= 1 ? 6 : (filteredLabels.length > 45 ? 1 : 4);
    });

    chartInstance.update();
}

// Controls (Tabs, Range, Select All)
function setupControls() {
    // Group Tabs
    const tabs = document.querySelectorAll('#group-tabs .tab-btn');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeGroup = tab.dataset.group;
            filterLegendByGroup();
        });
    });

    // Time Range Buttons
    const rangeBtns = document.querySelectorAll('#time-range-group .pill-btn');
    rangeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            rangeBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            applyTimeRange(btn.dataset.range);
        });
    });

    // Select / Deselect All
    document.getElementById('btn-select-all').addEventListener('click', () => {
        document.querySelectorAll('.legend-chip').forEach(chip => {
            if (chip.style.display !== 'none') {
                chip.classList.remove('disabled');
                toggleDatasetVisibility(chip.dataset.categoryId, true);
            }
        });
    });

    document.getElementById('btn-deselect-all').addEventListener('click', () => {
        document.querySelectorAll('.legend-chip').forEach(chip => {
            if (chip.style.display !== 'none') {
                chip.classList.add('disabled');
                toggleDatasetVisibility(chip.dataset.categoryId, false);
            }
        });
    });
}

// Modal: Collect Data Now
function setupModal() {
    const modal = document.getElementById('collect-modal');
    const btnOpen = document.getElementById('btn-collect-now');
    const btnClose = document.getElementById('modal-close-btn');
    const btnCancel = document.getElementById('modal-cancel-btn');
    const form = document.getElementById('collect-form');
    const repoInput = document.getElementById('gh-repo');
    const tokenInput = document.getElementById('gh-token');
    const rememberCheck = document.getElementById('remember-token');
    const progressBox = document.getElementById('collect-progress');
    const progressText = document.getElementById('progress-text');
    const btnTrigger = document.getElementById('btn-trigger-workflow');
    const toggleTokenBtn = document.getElementById('toggle-token-visibility');

    // Restore saved credentials
    const savedRepo = localStorage.getItem('cvbankas_gh_repo');
    const savedToken = localStorage.getItem('cvbankas_gh_token');
    if (savedRepo) repoInput.value = savedRepo;
    if (savedToken) tokenInput.value = savedToken;

    btnOpen.addEventListener('click', () => {
        modal.classList.remove('hidden');
        progressBox.classList.add('hidden');
        btnTrigger.disabled = false;
    });

    const closeModal = () => modal.classList.add('hidden');
    btnClose.addEventListener('click', closeModal);
    btnCancel.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    toggleTokenBtn.addEventListener('click', () => {
        tokenInput.type = tokenInput.type === 'password' ? 'text' : 'password';
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const repo = repoInput.value.trim();
        const token = tokenInput.value.trim();

        if (rememberCheck.checked) {
            localStorage.setItem('cvbankas_gh_repo', repo);
            localStorage.setItem('cvbankas_gh_token', token);
        } else {
            localStorage.removeItem('cvbankas_gh_repo');
            localStorage.removeItem('cvbankas_gh_token');
        }

        btnTrigger.disabled = true;
        progressBox.classList.remove('hidden');
        progressText.textContent = 'Triggering GitHub Actions workflow...';

        try {
            const dispatchUrl = `https://api.github.com/repos/${repo}/actions/workflows/daily.yml/dispatches`;
            const dispatchRes = await fetch(dispatchUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/vnd.github+json',
                    'Authorization': `Bearer ${token}`,
                    'X-GitHub-Api-Version': '2022-11-28'
                },
                body: JSON.stringify({ ref: 'main' })
            });

            if (!dispatchRes.ok) {
                const errJson = await dispatchRes.json().catch(() => ({}));
                throw new Error(errJson.message || `HTTP ${dispatchRes.status}: Unable to trigger workflow`);
            }

            progressText.textContent = 'Workflow triggered! Polling runner status (~45-60s)...';
            await pollWorkflowStatus(repo, token, progressText);
            progressText.textContent = 'Collection complete! Reloading latest data...';
            await loadData();
            setTimeout(closeModal, 1200);

        } catch (err) {
            console.error('Trigger error:', err);
            progressText.textContent = `Error: ${err.message}`;
            btnTrigger.disabled = false;
        }
    });
}

async function pollWorkflowStatus(repo, token, textElem) {
    const runsUrl = `https://api.github.com/repos/${repo}/actions/runs?event=workflow_dispatch&per_page=1`;
    let attempts = 0;

    while (attempts < 30) {
        await new Promise(r => setTimeout(r, 6000));
        attempts++;
        try {
            const res = await fetch(runsUrl, {
                headers: {
                    'Accept': 'application/vnd.github+json',
                    'Authorization': `Bearer ${token}`
                }
            });
            if (res.ok) {
                const data = await res.json();
                const latestRun = data.workflow_runs?.[0];
                if (latestRun) {
                    textElem.textContent = `Status: ${latestRun.status} (${latestRun.conclusion || 'running'})...`;
                    if (latestRun.status === 'completed') {
                        if (latestRun.conclusion === 'success') {
                            return true;
                        } else {
                            throw new Error(`Workflow completed with status: ${latestRun.conclusion}`);
                        }
                    }
                }
            }
        } catch (e) {
            console.warn('Poll status error:', e);
        }
    }
    return true;
}

// Others Table Logic
function initOthersTable() {
    filteredOthers = [...othersData];
    othersCurrentPage = 1;

    const countElem = document.getElementById('others-count');
    if (countElem) {
        countElem.textContent = othersData.length;
    }

    const searchInput = document.getElementById('others-search');
    if (searchInput) {
        searchInput.value = '';
        searchInput.oninput = (e) => {
            const query = e.target.value.toLowerCase().trim();
            if (!query) {
                filteredOthers = [...othersData];
            } else {
                filteredOthers = othersData.filter(item => 
                    (item.label && item.label.toLowerCase().includes(query)) ||
                    (item.date && item.date.toLowerCase().includes(query))
                );
            }
            othersCurrentPage = 1;
            renderOthersTable();
        };
    }

    const prevBtn = document.getElementById('others-prev-btn');
    const nextBtn = document.getElementById('others-next-btn');

    if (prevBtn) {
        prevBtn.onclick = () => {
            if (othersCurrentPage > 1) {
                othersCurrentPage--;
                renderOthersTable();
            }
        };
    }

    if (nextBtn) {
        nextBtn.onclick = () => {
            const maxPage = Math.ceil(filteredOthers.length / OTHERS_PAGE_SIZE) || 1;
            if (othersCurrentPage < maxPage) {
                othersCurrentPage++;
                renderOthersTable();
            }
        };
    }

    renderOthersTable();
}

function renderOthersTable() {
    const tbody = document.getElementById('others-tbody');
    const emptyState = document.getElementById('others-empty');
    const table = document.getElementById('others-table');
    const pageInfo = document.getElementById('others-page-info');
    const pageIndicator = document.getElementById('others-current-page');
    const prevBtn = document.getElementById('others-prev-btn');
    const nextBtn = document.getElementById('others-next-btn');
    const countElem = document.getElementById('others-count');

    if (countElem) {
        countElem.textContent = othersData.length;
    }

    if (!tbody) return;

    const total = filteredOthers.length;
    const maxPage = Math.ceil(total / OTHERS_PAGE_SIZE) || 1;
    if (othersCurrentPage > maxPage) othersCurrentPage = maxPage;

    if (total === 0) {
        tbody.innerHTML = '';
        if (emptyState) emptyState.classList.remove('hidden');
        if (table) table.style.display = 'none';
        if (pageInfo) pageInfo.textContent = 'Showing 0-0 of 0';
        if (pageIndicator) pageIndicator.textContent = '1 / 1';
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
        return;
    }

    if (emptyState) emptyState.classList.add('hidden');
    if (table) table.style.display = 'table';

    const startIdx = (othersCurrentPage - 1) * OTHERS_PAGE_SIZE;
    const endIdx = Math.min(startIdx + OTHERS_PAGE_SIZE, total);
    const pageItems = filteredOthers.slice(startIdx, endIdx);

    tbody.innerHTML = pageItems.map(item => {
        const safeLabel = escapeHtml(item.label || 'Untitled vacancy');
        const safeDate = escapeHtml(item.date || '-');
        const safeLink = escapeHtml(item.link || '#');
        return `
            <tr>
                <td class="col-date"><span class="date-badge">${safeDate}</span></td>
                <td class="col-label">
                    <a href="${safeLink}" target="_blank" rel="noopener noreferrer" class="vacancy-link">
                        ${safeLabel}
                    </a>
                </td>
            </tr>
        `;
    }).join('');

    if (pageInfo) {
        pageInfo.textContent = `Showing ${startIdx + 1}-${endIdx} of ${total}`;
    }
    if (pageIndicator) {
        pageIndicator.textContent = `${othersCurrentPage} / ${maxPage}`;
    }
    if (prevBtn) prevBtn.disabled = (othersCurrentPage <= 1);
    if (nextBtn) nextBtn.disabled = (othersCurrentPage >= maxPage);
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

