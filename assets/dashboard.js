(() => {
	'use strict';

	const periodSelect = document.querySelector('.wzl-period-form select[name="wzl_period"]');
	if (periodSelect) {
		const dates = periodSelect.form.querySelector('.wzl-period-dates');
		const inputs = dates.querySelectorAll('input');

		function updateDateFields() {
			const custom = periodSelect.value === 'custom';
			dates.hidden = !custom;
			inputs.forEach((input) => {
				input.disabled = !custom;
				input.required = custom;
			});
		}

		periodSelect.addEventListener('change', updateDateFields);
		updateDateFields();
	}

	const canvas = document.querySelector('.webzero-leads-dashboard .wzl-line-chart');
	if (!canvas) {
		return;
	}

	const context = canvas.getContext('2d');
	const { points: counts, ticks, lastIndex } = JSON.parse(canvas.dataset.chart);
	if (!context) {
		return;
	}

	const points = Object.entries(counts).map(([index, count]) => ({ index: Number(index), count }));
	const largest = points.reduce((maximum, point) => Math.max(maximum, point.count), 0);
	const unit = 10 ** Math.floor(Math.log10(Math.max(1, largest / 4)));
	const step = [1, 2, 5, 10].find((value) => value * unit >= largest / 4) * unit;
	const scale = Math.max(step, Math.ceil(largest / step) * step);
	const formatNumber = new Intl.NumberFormat(document.documentElement.lang || undefined);

	function draw() {
		const { width, height } = canvas.getBoundingClientRect();
		if (!width || !height) {
			return;
		}

		const density = Math.min(2, window.devicePixelRatio || 1);
		canvas.width = Math.round(width * density);
		canvas.height = Math.round(height * density);
		context.setTransform(density, 0, 0, density, 0, 0);
		context.font = '12px system-ui, sans-serif';

		const left = Math.ceil(context.measureText(formatNumber.format(scale)).width) + 12;
		const right = width - 8;
		const top = 14;
		const bottom = height - 28;
		const x = (index) => lastIndex ? left + (right - left) * index / lastIndex : (left + right) / 2;
		const y = (value) => bottom - (bottom - top) * value / scale;

		context.fillStyle = '#526261';
		context.strokeStyle = '#e3eae5';
		context.textAlign = 'right';
		context.textBaseline = 'middle';
		context.beginPath();
		for (let tick = 0; tick <= scale; tick += step) {
			const position = y(tick);
			context.moveTo(left, position);
			context.lineTo(right, position);
			context.fillText(formatNumber.format(tick), left - 8, position);
		}
		context.stroke();

		const labelCount = Math.min(ticks.length, width >= 560 ? 5 : width >= 320 ? 3 : 2);
		context.textBaseline = 'top';
		for (let label = 0; label < labelCount; label++) {
			const tick = ticks[labelCount === 1 ? 0 : Math.round(label * (ticks.length - 1) / (labelCount - 1))];
			context.textAlign = tick.index === 0 && lastIndex ? 'left' : tick.index === lastIndex && lastIndex ? 'right' : 'center';
			context.fillText(tick.label, x(tick.index), bottom + 9);
		}

		function trace() {
			context.beginPath();
			context.moveTo(x(0), y(points[0].count));
			for (let position = 1; position < points.length; position++) {
				const previous = points[position - 1].index;
				const point = points[position];
				if (point.index - previous > 1) {
					context.lineTo(x(previous + 1), bottom);
					context.lineTo(x(point.index - 1), bottom);
				}
				context.lineTo(x(point.index), y(point.count));
			}
		}

		if (lastIndex) {
			trace();
			context.lineTo(right, bottom);
			context.lineTo(left, bottom);
			context.closePath();
			const area = context.createLinearGradient(0, top, 0, bottom);
			area.addColorStop(0, '#a3e63566');
			area.addColorStop(1, '#eaf5de40');
			context.fillStyle = area;
			context.fill();

			trace();
			context.lineWidth = 2;
			context.lineJoin = 'round';
			context.strokeStyle = '#3d702a';
			context.stroke();
		}

		context.fillStyle = '#3d702a';
		points.forEach((point) => {
			if (point.count > 0) {
				context.beginPath();
				context.arc(x(point.index), y(point.count), lastIndex ? 2.5 : 4, 0, 2 * Math.PI);
				context.fill();
			}
		});
	}

	canvas.hidden = false;
	if ('ResizeObserver' in window) {
		new ResizeObserver(draw).observe(canvas);
	} else {
		draw();
		window.addEventListener('resize', draw);
	}
})();
