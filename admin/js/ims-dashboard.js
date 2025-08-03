jQuery( function($) {
  const ctx1 = document.getElementById('chart-status-ratio').getContext('2d');
  new Chart(ctx1, {
    type: 'pie',
    data: {
      labels: imsDashboardData.statusRatio.labels,
      datasets: [{ data: imsDashboardData.statusRatio.data }]
    }
  });

  const ctx2 = document.getElementById('chart-amount-status').getContext('2d');
  new Chart(ctx2, {
    type: 'bar',
    data: {
      labels: imsDashboardData.amountByStatus.labels,
      datasets: [{ label: 'Amount', data: imsDashboardData.amountByStatus.data }]
    }
  });

  const ctx3 = document.getElementById('chart-amount-timeline').getContext('2d');
  new Chart(ctx3, {
    type: 'histogram', // Chart.js v3 plugin needed or use bar
    data: {
      labels: imsDashboardData.amountTimeline.labels,
      datasets: [{ label: 'Amount', data: imsDashboardData.amountTimeline.data }]
    }
  });

  const ctx4 = document.getElementById('chart-count-month').getContext('2d');
  new Chart(ctx4, {
    type: 'bar',
    data: {
      labels: imsDashboardData.countByMonth.labels,
      datasets: imsDashboardData.countByMonth.series.map(s=>({
        label: s.name,
        data: s.data,
        backgroundColor: undefined // defaults
      }))
    },
    options: { scales: { x: { stacked: true }, y: { stacked: true } } }
  });

  // Projects pie
  const ctx5 = document.getElementById('chart-project-ratio').getContext('2d');
  new Chart(ctx5, {
    type: 'pie',
    data: {
      labels: imsDashboardData.projectRatio.labels,
      datasets: [{ data: imsDashboardData.projectRatio.data }]
    }
  });

  // Locations pie
  const ctx6 = document.getElementById('chart-location-ratio').getContext('2d');
  new Chart(ctx6, {
    type: 'pie',
    data: {
      labels: imsDashboardData.locationRatio.labels,
      datasets: [{ data: imsDashboardData.locationRatio.data }]
    }
  });

  // Count by project stacked
  const ctx7 = document.getElementById('chart-count-project').getContext('2d');
  new Chart(ctx7, {
    type: 'bar',
    data: {
      labels: imsDashboardData.countByProject.labels,
      datasets: imsDashboardData.countByProject.series.map(s=>({
        label: s.name,
        data: s.data
      }))
    },
    options: { indexAxis: 'y', scales: { x: { stacked: true }, y: { stacked: true } } }
  });

  // Count by location stacked
  const ctx8 = document.getElementById('chart-count-location').getContext('2d');
  new Chart(ctx8, {
    type: 'bar',
    data: {
      labels: imsDashboardData.countByLocation.labels,
      datasets: imsDashboardData.countByLocation.series.map(s=>({
        label: s.name,
        data: s.data
      }))
    },
    options: { indexAxis: 'y', scales: { x: { stacked: true }, y: { stacked: true } } }
  });
});
