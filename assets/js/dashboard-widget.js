/**
 * Pantheon metrics dashboard widget.
 *
 * Renders a compact cache-hit-ratio line chart over time and summary
 * stats using the existing ash_nazg_get_metrics AJAX action.
 */
(function ($) {
	'use strict';

	if (typeof ashNazgWidget === 'undefined') {
		return;
	}

	var chart = null;

	function renderChart(timeseries) {
		var ctx = document.getElementById('ash-nazg-widget-chart');
		if (!ctx) {
			return;
		}

		if (chart) {
			chart.destroy();
		}

		var labels = [];
		var ratioData = [];

		timeseries.forEach(function (point) {
			var hits = point.cache_hits || 0;
			var misses = point.cache_misses || 0;
			var total = hits + misses;
			var ratio = total > 0 ? Math.round((hits / total) * 100) : 0;
			labels.push(point.datetime ? point.datetime.substring(0, 10) : '');
			ratioData.push(ratio);
		});

		chart = new Chart(ctx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [{
					label: ashNazgWidget.i18n.cacheHitRatio,
					data: ratioData,
					borderColor: '#00a32a',
					backgroundColor: 'rgba(0, 163, 42, 0.1)',
					borderWidth: 2,
					pointRadius: 0,
					pointHoverRadius: 4,
					fill: true,
					tension: 0.4
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						callbacks: {
							label: function (context) {
								return context.parsed.y + '%';
							}
						}
					}
				},
				scales: {
					x: {
						display: false
					},
					y: {
						display: true,
						min: 0,
						max: 100,
						ticks: {
							maxTicksLimit: 3,
							callback: function (value) {
								return value + '%';
							},
							font: { size: 10 }
						},
						grid: {
							color: 'rgba(0,0,0,0.06)'
						}
					}
				}
			}
		});
	}

	function updateStats(timeseries) {
		var totalPages = 0;
		var totalHits = 0;
		var totalMisses = 0;

		timeseries.forEach(function (point) {
			totalPages += (point.pages_served || 0);
			totalHits += (point.cache_hits || 0);
			totalMisses += (point.cache_misses || 0);
		});

		var total = totalHits + totalMisses;
		var ratio = total > 0 ? Math.round((totalHits / total) * 100) : 0;

		$('#ash-nazg-widget-ratio').text(ratio + '%');
		$('#ash-nazg-widget-pages').text(totalPages.toLocaleString());

		renderChart(timeseries);
	}

	function loadMetrics() {
		$('#ash-nazg-widget-loading').show();
		$('#ash-nazg-widget-content').hide();
		$('#ash-nazg-widget-error').hide();

		$.ajax({
			url: ashNazgWidget.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_get_metrics',
				nonce: ashNazgWidget.nonce,
				environment: ashNazgWidget.environment,
				duration: '28d'
			},
			success: function (response) {
				$('#ash-nazg-widget-loading').hide();

				if (response.success && response.data && response.data.metrics && response.data.metrics.timeseries) {
					updateStats(response.data.metrics.timeseries);
					$('#ash-nazg-widget-content').show();
				} else {
					$('#ash-nazg-widget-error').show();
				}
			},
			error: function () {
				$('#ash-nazg-widget-loading').hide();
				$('#ash-nazg-widget-error').show();
			}
		});
	}

	$(document).ready(function () {
		loadMetrics();
	});

})(jQuery);
