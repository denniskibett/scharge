import ApexCharts from "apexcharts";

/*
 * Charts for /reports and the dashboard's analytics summary. Each reads its numbers from a
 * <script type="application/json"> block on the page, so it works after background navigation too.
 */
const charts = [];
const kes = (v) => "KES " + Math.round(v).toLocaleString();
const compact = (v) => (v >= 1e6 ? (v / 1e6).toFixed(1) + "M" : v >= 1e3 ? Math.round(v / 1e3) + "k" : String(Math.round(v)));
const dark = () => document.documentElement.classList.contains("dark") || document.body.classList.contains("dark");
const base = () => ({
  chart: { fontFamily: "Outfit, sans-serif", toolbar: { show: false }, background: "transparent" },
  theme: { mode: dark() ? "dark" : "light" },
  dataLabels: { enabled: false },
  grid: { borderColor: dark() ? "#1f2937" : "#e5e7eb", strokeDashArray: 4 },
  legend: { position: "top", horizontalAlign: "left", fontSize: "13px" },
});
const draw = (id, options) => {
  const el = document.getElementById(id);
  if (!el) return;
  const c = new ApexCharts(el, { ...base(), ...options, chart: { ...base().chart, ...options.chart } });
  c.render();
  charts.push(c);
};

function readData(id) {
  const el = document.getElementById(id);
  if (!el) return null;
  try { return JSON.parse(el.textContent); } catch { return null; }
}

export default function reportCharts() {
  while (charts.length) charts.pop().destroy();

  const d = readData("reports-data");
  if (d) {
    draw("report-monthly", {
      chart: { type: "bar", height: 300 },
      series: [
        { name: "Billed", data: d.monthly.map((m) => m.billed) },
        { name: "Collected", data: d.monthly.map((m) => m.collected) },
        ...(d.monthly.some((m) => m.expenses > 0) ? [{ name: "Expenses", data: d.monthly.map((m) => m.expenses) }] : []),
      ],
      colors: ["#465fff", "#12b76a", "#f79009"],
      plotOptions: { bar: { columnWidth: "55%", borderRadius: 4, borderRadiusApplication: "end" } },
      xaxis: { categories: d.monthly.map((m) => m.month), axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: { labels: { formatter: compact } },
      tooltip: { y: { formatter: kes } },
    });
    draw("report-methods", {
      chart: { type: "donut", height: 300 },
      series: d.methods.map((m) => m.total),
      labels: d.methods.map((m) => m.method),
      colors: ["#465fff", "#12b76a", "#f79009", "#7a5af8", "#ee46bc", "#0ba5ec", "#667085"],
      legend: { position: "bottom" },
      plotOptions: { pie: { donut: { size: "65%", labels: { show: true, total: { show: true, label: "Received", formatter: (w) => compact(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) } } } } },
      tooltip: { y: { formatter: kes } },
      noData: { text: "No payments in this period" },
    });
    draw("report-occupancy", {
      chart: { type: "bar", height: Math.max(220, 60 + d.properties.length * 42), stacked: true },
      series: [
        { name: "Occupied", data: d.properties.map((p) => p.occupied) },
        { name: "Vacant", data: d.properties.map((p) => p.units - p.occupied) },
      ],
      colors: ["#12b76a", "#d0d5dd"],
      plotOptions: { bar: { horizontal: true, barHeight: "60%", borderRadius: 3 } },
      xaxis: { categories: d.properties.map((p) => p.name) },
      tooltip: { y: { formatter: (v) => v + " units" } },
    });
    draw("report-aging", {
      chart: { type: "bar", height: 280 },
      series: [{ name: "Owed", data: d.aging.map((a) => a.total) }],
      colors: ["#f04438"],
      plotOptions: { bar: { columnWidth: "50%", borderRadius: 4, borderRadiusApplication: "end", distributed: false } },
      xaxis: { categories: d.aging.map((a) => a.bucket), axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: { labels: { formatter: compact } },
      tooltip: { y: { formatter: (v, o) => kes(v) + " · " + d.aging[o.dataPointIndex].count + " bills" } },
    });
  }

  const s = readData("dash-analytics-data");
  if (s) {
    draw("dash-analytics-chart", {
      chart: { type: "area", height: 180, sparkline: { enabled: false } },
      series: [
        { name: "Billed", data: s.map((m) => m.billed) },
        { name: "Collected", data: s.map((m) => m.collected) },
      ],
      colors: ["#465fff", "#12b76a"],
      stroke: { curve: "monotoneCubic", width: 2 },
      fill: { type: "gradient", gradient: { opacityFrom: 0.35, opacityTo: 0 } },
      xaxis: { categories: s.map((m) => m.month), axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: { labels: { formatter: compact } },
      tooltip: { y: { formatter: kes } },
      legend: { show: false },
    });
  }
}
