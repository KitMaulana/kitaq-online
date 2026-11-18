/** Grafik Chart.js lokal dengan warna token KITAQ. Pemakaian: <x-ui.chart type="bar" :labels="[...]" :datasets="[...]" /> */
import {
  Chart, BarController, LineController, DoughnutController, BarElement, LineElement, PointElement, ArcElement,
  CategoryScale, LinearScale, Tooltip, Legend, Filler,
} from 'chart.js';

Chart.register(BarController, LineController, DoughnutController, BarElement, LineElement, PointElement, ArcElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);

const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
const PALETTE = ['--primary', '--info', '--success', '--warning', '--accent', '--neutral'];

export function registerCharts(Alpine) {
  Alpine.data('kqChart', (config) => ({
    chart: null,
    init() {
      this.render();
      window.addEventListener('kq:theme', () => this.render());
      this.$el.addEventListener('kq:chart-update', (e) => { config = e.detail; this.render(); });
    },
    render() {
      if (this.chart) this.chart.destroy();
      const text = css('--text-muted');
      const grid = css('--border');
      const datasets = (config.datasets || []).map((d, i) => {
        const c = css(PALETTE[i % PALETTE.length]);
        const many = config.type === 'doughnut' ? (d.data || []).map((_, j) => css(PALETTE[j % PALETTE.length])) : null;
        return { borderRadius: config.type === 'bar' ? 6 : 0, tension: 0.35, fill: config.type === 'line', ...d,
          backgroundColor: d.backgroundColor || many || (config.type === 'line' ? c + '22' : c),
          borderColor: d.borderColor || (config.type === 'doughnut' ? css('--surface') : c) };
      });
      this.chart = new Chart(this.$refs.canvas, {
        type: config.type || 'bar',
        data: { labels: config.labels || [], datasets },
        options: {
          responsive: true, maintainAspectRatio: false, animation: { duration: 400 },
          plugins: { legend: { display: datasets.length > 1 || config.type === 'doughnut', labels: { color: text, boxWidth: 10 } } },
          scales: config.type === 'doughnut' ? {} : {
            x: { ticks: { color: text }, grid: { display: false } },
            y: { ticks: { color: text, precision: 0 }, grid: { color: grid }, beginAtZero: true, max: config.max },
          },
        },
      });
    },
    destroy() { if (this.chart) this.chart.destroy(); },
  }));
}
