(function($) {
	'use strict';

	let pagesServedChart = null;
	let uniqueVisitsChart = null;
	let cachePerformanceChart = null;

	/**
	 * Load metrics data via AJAX.
	 */
	function loadMetrics() {
		const environment = $('#metrics-environment').val();
		const duration = $('#metrics-duration').val();

		$('#metrics-loading').show();
		$('#metrics-error').hide();
		$('#metrics-charts').hide();

		$.ajax({
			url: ashNazgMetrics.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_get_metrics',
				nonce: ashNazgMetrics.metricsNonce,
				environment: environment,
				duration: duration
			},
			success: function(response) {
				$('#metrics-loading').hide();
				$('#metrics-error').hide();

				// Display raw API response for debugging.
				displayRawResponse(environment, duration, response);

				if (response.success && response.data.metrics && response.data.metrics.timeseries) {
					try {
						renderCharts(response.data.metrics);
						calculateSummary(response.data.metrics);
						$('#metrics-charts').show();
					} catch (error) {
						console.error('Error rendering charts:', error);
						showError('Failed to render charts: ' + error.message);
					}
				} else {
					console.error('Invalid response structure:', response);
					showError(response.data.message || ashNazgMetrics.i18n.loadingError);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				$('#metrics-loading').hide();

				// Display error details for debugging.
				const errorResponse = {
					error: true,
					status: textStatus,
					message: errorThrown,
					responseText: jqXHR.responseText
				};
				displayRawResponse(environment, duration, errorResponse);

				showError(ashNazgMetrics.i18n.loadingError);
			}
		});
	}

	/**
	 * Refresh metrics (clear cache and reload).
	 */
	function refreshMetrics() {
		const environment = $('#metrics-environment').val();

		$('#metrics-loading').show();
		$('#metrics-error').hide();

		$.ajax({
			url: ashNazgMetrics.ajaxUrl,
			type: 'POST',
			data: {
				action: 'ash_nazg_refresh_metrics',
				nonce: ashNazgMetrics.refreshNonce,
				environment: environment
			},
			success: function(response) {
				if (response.success) {
					// Cache cleared, now reload metrics.
					loadMetrics();
				} else {
					$('#metrics-loading').hide();
					showError(response.data.message || ashNazgMetrics.i18n.refreshError);
				}
			},
			error: function() {
				$('#metrics-loading').hide();
				showError(ashNazgMetrics.i18n.refreshError);
			}
		});
	}

	/**
	 * Render charts with metrics data.
	 *
	 * @param {Object} metricsData Metrics data from API.
	 */
	function renderCharts(metricsData) {
		// Parse metrics data structure.
		// Actual structure: { timeseries: [ { timestamp, datetime, pages_served, visits, cache_hits, cache_misses }, ... ] }

		if (!metricsData || !metricsData.timeseries || metricsData.timeseries.length === 0) {
			console.error('Invalid metrics data or empty timeseries');
			showError(ashNazgMetrics.i18n.noData);
			return;
		}

		const metrics = metricsData.timeseries;

		// Extract data for charts.
		const labels = [];
		const pagesServedData = [];
		const visitsData = [];
		const cacheHitsData = [];
		const cacheMissesData = [];

		metrics.forEach(function(metric) {
			// Parse timestamp (Unix timestamp in seconds) to readable format.
			const date = new Date(metric.timestamp * 1000);
			const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
			labels.push(dateStr);

			pagesServedData.push(metric.pages_served || 0);
			visitsData.push(metric.visits || 0);
			cacheHitsData.push(metric.cache_hits || 0);
			cacheMissesData.push(metric.cache_misses || 0);
		});

		// Destroy existing charts if they exist.
		if (pagesServedChart) pagesServedChart.destroy();
		if (uniqueVisitsChart) uniqueVisitsChart.destroy();
		if (cachePerformanceChart) cachePerformanceChart.destroy();

		// Check if Chart.js is loaded.
		if (typeof Chart === 'undefined') {
			console.error('Chart.js is not loaded!');
			showError('Chart.js library failed to load. Please refresh the page.');
			return;
		}

		// Pantheon Design System colors.
		const pdsColors = {
			blue: {
				border: 'rgb(28, 151, 234)',
				background: 'rgba(28, 151, 234, 0.08)'
			},
			purple: {
				border: 'rgb(255, 212, 69)',
				background: 'rgba(255, 212, 69, 0.08)'
			},
			green: {
				border: 'rgb(126, 200, 53)',
				background: 'rgba(126, 200, 53, 0.08)'
			},
			red: {
				border: 'rgb(237, 67, 55)',
				background: 'rgba(237, 67, 55, 0.08)'
			}
		};

		// Common chart.js configuration options.
		const commonOptions = {
			responsive: true,
			maintainAspectRatio: true,
			interaction: {
				intersect: false,
				mode: 'index'
			},
			plugins: {
				legend: {
					display: false
				},
				tooltip: {
					backgroundColor: 'rgba(0, 0, 0, 0.8)',
					padding: 12,
					titleFont: {
						size: 14,
						weight: 'bold'
					},
					bodyFont: {
						size: 13
					},
					borderColor: 'rgba(255, 255, 255, 0.1)',
					borderWidth: 1
				}
			},
			scales: {
				x: {
					grid: {
						display: false
					},
					ticks: {
						font: {
							size: 11
						}
					}
				},
				y: {
					beginAtZero: true,
					grid: {
						color: 'rgba(0, 0, 0, 0.05)'
					},
					ticks: {
						font: {
							size: 11
						}
					}
				}
			}
		};

		// Pages Served Chart.
		const pagesCtx = document.getElementById('pages-served-chart').getContext('2d');
		pagesServedChart = new Chart(pagesCtx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [{
					label: 'Pages Served',
					data: pagesServedData,
					borderColor: pdsColors.blue.border,
					backgroundColor: pdsColors.blue.background,
					borderWidth: 3,
					pointRadius: 4,
					pointHoverRadius: 6,
					pointBackgroundColor: pdsColors.blue.border,
					pointBorderColor: '#fff',
					pointBorderWidth: 2,
					fill: true,
					tension: 0.4
				}]
			},
			options: commonOptions
		});

		// Unique Visits Chart.
		const visitsCtx = document.getElementById('unique-visits-chart').getContext('2d');
		uniqueVisitsChart = new Chart(visitsCtx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [{
					label: 'Unique Visits',
					data: visitsData,
					borderColor: pdsColors.purple.border,
					backgroundColor: pdsColors.purple.background,
					borderWidth: 3,
					pointRadius: 4,
					pointHoverRadius: 6,
					pointBackgroundColor: pdsColors.purple.border,
					pointBorderColor: '#fff',
					pointBorderWidth: 2,
					fill: true,
					tension: 0.4
				}]
			},
			options: commonOptions
		});

		// Cache Performance Chart.
		const cacheCtx = document.getElementById('cache-performance-chart').getContext('2d');

		// Clone common options and enable legend for cache chart.
		const cacheOptions = JSON.parse(JSON.stringify(commonOptions));
		cacheOptions.plugins.legend = {
			display: true,
			position: 'top',
			labels: {
				usePointStyle: true,
				padding: 15,
				font: {
					size: 12
				}
			}
		};

		cachePerformanceChart = new Chart(cacheCtx, {
			type: 'line',
			data: {
				labels: labels,
				datasets: [
					{
						label: 'Cache Hits',
						data: cacheHitsData,
						borderColor: pdsColors.green.border,
						backgroundColor: pdsColors.green.background,
						borderWidth: 3,
						pointRadius: 4,
						pointHoverRadius: 6,
						pointBackgroundColor: pdsColors.green.border,
						pointBorderColor: '#fff',
						pointBorderWidth: 2,
						fill: true,
						tension: 0.4
					},
					{
						label: 'Cache Misses',
						data: cacheMissesData,
						borderColor: pdsColors.red.border,
						backgroundColor: pdsColors.red.background,
						borderWidth: 3,
						pointRadius: 4,
						pointHoverRadius: 6,
						pointBackgroundColor: pdsColors.red.border,
						pointBorderColor: '#fff',
						pointBorderWidth: 2,
						fill: true,
						tension: 0.4
					}
				]
			},
			options: cacheOptions
		});
	}

	/**
	 * Calculate summary statistics.
	 *
	 * @param {Object} metricsData Metrics data from API.
	 */
	function calculateSummary(metricsData) {
		if (!metricsData || !metricsData.timeseries || metricsData.timeseries.length === 0) {
			return;
		}

		const metrics = metricsData.timeseries;

		let totalPages = 0;
		let totalVisits = 0;
		let totalCacheHits = 0;
		let totalCacheMisses = 0;

		metrics.forEach(function(metric) {
			totalPages += metric.pages_served || 0;
			totalVisits += metric.visits || 0;
			totalCacheHits += metric.cache_hits || 0;
			totalCacheMisses += metric.cache_misses || 0;
		});

		// Calculate average cache hit ratio.
		const totalCacheRequests = totalCacheHits + totalCacheMisses;
		const avgCacheRatio = totalCacheRequests > 0
			? ((totalCacheHits / totalCacheRequests) * 100).toFixed(2)
			: 0;

		// Update summary stats (top section).
		$('#total-pages-served').text(totalPages.toLocaleString());
		$('#total-unique-visits').text(totalVisits.toLocaleString());
		$('#avg-cache-ratio').text(avgCacheRatio + '%');

		// Update individual chart summaries.
		$('#chart-total-pages').text(totalPages.toLocaleString());
		$('#chart-total-visits').text(totalVisits.toLocaleString());
		$('#chart-cache-ratio').text(avgCacheRatio + '%');
	}

	/**
	 * Show error message.
	 *
	 * @param {string} message Error message to display.
	 */
	function showError(message) {
		$('#metrics-error p').text(message);
		$('#metrics-error').show();
	}

	/**
	 * Display raw API response for debugging.
	 *
	 * @param {string} environment Environment name.
	 * @param {string} duration Duration parameter.
	 * @param {Object} response AJAX response object.
	 */
	function displayRawResponse(environment, duration, response) {
		// Construct the API endpoint URL (approximation for display).
		const siteId = 'SITE_ID'; // Placeholder - actual site ID used server-side.
		const endpoint = '/v0/sites/' + siteId + '/environments/' + environment + '/metrics?duration=' + duration;

		$('#api-endpoint').text(endpoint);
		$('#api-response').text(JSON.stringify(response, null, 2));
		$('#metrics-raw-data').show();
	}

	// Event handlers.
	$('#load-metrics').on('click', function(e) {
		e.preventDefault();
		loadMetrics();
	});

	$('#refresh-metrics').on('click', function(e) {
		e.preventDefault();
		refreshMetrics();
	});

	// Auto-load on page load with default selections.
	$(document).ready(function() {
		loadMetrics();
	});

})(jQuery);
