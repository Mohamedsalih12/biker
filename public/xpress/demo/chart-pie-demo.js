// Set new default font family and font color to mimic Bootstrap's default styling (defensive: may not exist)
try {
  if (Chart && Chart.defaults) {
    if (Chart.defaults.global) {
      Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
      Chart.defaults.global.defaultFontColor = '#292b2c';
    }
  }
} catch (e) {}

// Pie Chart Example (only initialize when element and Chart are available)
var ctx = document.getElementById("myPieChart");
if (ctx && typeof Chart !== 'undefined') {
  try {
    var myPieChart = new Chart(ctx, {
      type: 'pie',
      data: {
        labels: ["Blue", "Red", "Yellow", "Green"],
        datasets: [{
          data: [12.21, 15.58, 11.25, 8.32],
          backgroundColor: ['#007bff', '#dc3545', '#ffc107', '#28a745'],
        }],
      },
    });
  } catch (e) {
    console.warn('Pie chart init skipped:', e);
  }
}
