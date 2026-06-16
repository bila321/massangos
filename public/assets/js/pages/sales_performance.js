// ── Gráfico ──────────────────────────────────────────────
(function () {
    const canvas = document.getElementById('spChart');
    if (!canvas) return;

    const labels = <?= json_encode($chart_days) ?>;
    const revenue = <?= json_encode($chart_revenue) ?>;
    const counts = <?= json_encode($chart_counts) ?>;

    const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
    const tickColor = isDark ? '#85888c' : '#94a3b8';

    new Chart(canvas, {
        data: {
            labels,
            datasets: [{
                type: 'line',
                label: 'Receita (MZN)',
                data: revenue,
                borderColor: '#07c95b',
                backgroundColor: 'rgba(7,201,91,0.08)',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: '#07c95b',
                fill: true,
                tension: 0.4,
                yAxisID: 'y'
            },
            {
                type: 'bar',
                label: 'Nº de vendas',
                data: counts,
                backgroundColor: 'rgba(59,130,246,0.18)',
                borderColor: 'rgba(59,130,246,0.5)',
                borderWidth: 1,
                borderRadius: 4,
                yAxisID: 'y2'
            }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: isDark ? '#2c2c2c' : '#fff',
                    borderColor: isDark ? '#444' : '#e2e8f0',
                    borderWidth: 1,
                    titleColor: isDark ? '#eee' : '#000',
                    bodyColor: isDark ? '#aaa' : '#64748b',
                    padding: 12,
                    callbacks: {
                        label: ctx => ctx.dataset.yAxisID === 'y' ?
                            ` ${ctx.raw.toLocaleString('pt-MZ', { minimumFractionDigits: 2 })} MZN` : ` ${ctx.raw} venda${ctx.raw !== 1 ? 's' : ''}`
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: gridColor
                    },
                    ticks: {
                        color: tickColor,
                        font: {
                            size: 10
                        },
                        maxRotation: 0,
                        callback: (v, i) => i % 5 === 0 ? labels[i] : ''
                    }
                },
                y: {
                    position: 'left',
                    grid: {
                        color: gridColor
                    },
                    ticks: {
                        color: tickColor,
                        font: {
                            size: 10
                        },
                        callback: v => v.toLocaleString('pt-MZ', {
                            minimumFractionDigits: 0
                        }) + ' MT'
                    }
                },
                y2: {
                    position: 'right',
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        color: tickColor,
                        font: {
                            size: 10
                        },
                        stepSize: 1
                    }
                }
            }
        }
    });
})();

// ── Search + filter ──────────────────────────────────────
(function () {
    const searchInput = document.getElementById('itemSearch');
    const typeFilter = document.getElementById('typeFilter');
    const cards = document.querySelectorAll('#itemsList .item-card');

    function applyFilters() {
        const q = searchInput.value.toLowerCase().trim();
        const type = typeFilter.value;
        cards.forEach(card => {
            const matchQ = !q || card.dataset.title.includes(q);
            const matchType = type === 'all' || card.dataset.type === type;
            card.classList.toggle('hidden', !(matchQ && matchType));
        });
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (typeFilter) typeFilter.addEventListener('change', applyFilters);
})();