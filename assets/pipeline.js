(() => {
	'use strict';

	const board = document.querySelector('.wzl-pipeline-board');
	const config = window.webzeroLeadsPipeline;
	if (!board || !config) {
		return;
	}

	const wrapper = board.closest('.webzero-leads-pipeline');
	const statusMessage = wrapper.querySelector('.wzl-pipeline-status');
	const progress = wrapper.querySelector('progress');
	const tally = wrapper.querySelector('.wzl-pipeline-tally');
	const formatNumber = new Intl.NumberFormat(document.documentElement.lang || undefined);
	const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
	const stages = new Map();
	let pending = 0;
	let drag = null;
	let suppressClick = false;

	wrapper.querySelector('.wzl-pipeline-intro').hidden = false;
	board.classList.add('is-interactive');
	board.querySelectorAll('.wzl-card-actions').forEach((control) => {
		control.hidden = false;
	});
	board.querySelectorAll('.wzl-stage').forEach((column) => {
		stages.set(column.dataset.stage, {
			column,
			list: column.querySelector('.wzl-stage-cards'),
			count: column.querySelector('.wzl-stage-count'),
			empty: column.querySelector('.wzl-stage-empty'),
			label: column.querySelector('h2').textContent,
		});
	});
	const total = Array.from(stages.values()).reduce((sum, stage) => sum + stage.list.childElementCount, 0);

	function formatMessage(message, first, second) {
		return message.replace(/%[12]\$s/g, (placeholder) => placeholder === '%1$s' ? first : second);
	}

	function updateCount(stage) {
		const count = stage.list.childElementCount;
		stage.count.textContent = formatNumber.format(count);
		stage.empty.hidden = count > 0;
	}

	function placeCard(card, from, target) {
		const id = BigInt(card.dataset.leadId);
		const nextCard = Array.from(target.list.children).find((lead) => BigInt(lead.dataset.leadId) < id);
		target.list.insertBefore(card, nextCard || null);
		updateCount(from);
		updateCount(target);
		const completed = stages.get('finalizado').list.childElementCount;
		progress.value = completed;
		tally.textContent = formatMessage(progress.dataset.tallyFormat, formatNumber.format(completed), formatNumber.format(total));
	}

	function animatePlacement(card, from) {
		if (!from || reducedMotion.matches) {
			return;
		}

		const to = card.getBoundingClientRect();
		if (to.bottom < 0 || to.top > window.innerHeight) {
			return;
		}

		card.animate([
			{ transform: 'translate(' + (from.left - to.left) + 'px, ' + (from.top - to.top) + 'px)', boxShadow: '0 16px 32px #17264538', zIndex: 3 },
			{ transform: 'none', boxShadow: '', zIndex: 3 },
		], { duration: 240, easing: 'cubic-bezier(.2,.75,.3,1)' });
	}

	async function move(card, status, { restoreFocus = false, dragRect } = {}) {
		const previous = card.closest('.wzl-stage').dataset.stage;
		const origin = stages.get(previous);
		const target = stages.get(status);
		if (!target || previous === status || card.hasAttribute('aria-busy')) {
			return;
		}

		const select = card.querySelector('select');
		const button = card.querySelector('.wzl-move-lead');
		const errorMessage = card.querySelector('.wzl-pipeline-error');
		card.setAttribute('aria-busy', 'true');
		card.classList.remove('is-saved');
		select.disabled = true;
		select.value = status;
		button.disabled = true;
		errorMessage.textContent = '';
		pending += 1;
		statusMessage.classList.remove('is-error');
		statusMessage.textContent = config.saving;
		placeCard(card, origin, target);
		animatePlacement(card, dragRect);

		let failure = '';
		try {
			const response = await fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: new URLSearchParams({
					action: config.action,
					nonce: config.nonce,
					lead_id: card.dataset.leadId,
					previous,
					status,
				}),
			});
			const reply = await response.json();
			if (!response.ok || !reply?.success || reply.data?.status !== status) {
				failure = typeof reply?.data?.message === 'string' && reply.data.message ? reply.data.message : config.failed;
			}
		} catch {
			failure = config.failed;
		}

		pending -= 1;
		if (failure) {
			const from = card.getBoundingClientRect();
			card.getAnimations().forEach((animation) => animation.cancel());
			placeCard(card, target, origin);
			select.value = previous;
			animatePlacement(card, from);
			errorMessage.textContent = failure;
		} else {
			const name = card.querySelector('.wzl-card-link').textContent;
			statusMessage.textContent = formatMessage(config.saved, name, target.label);
			card.classList.add('is-saved');
		}
		if (failure || pending) {
			statusMessage.textContent = pending ? config.saving : failure;
		}
		statusMessage.classList.toggle('is-error', Boolean(failure && !pending));
		select.disabled = false;
		card.removeAttribute('aria-busy');
		if (restoreFocus && (document.activeElement === document.body || card.contains(document.activeElement))) {
			select.focus();
		}
	}

	board.addEventListener('change', (event) => {
		if (event.target.matches('.wzl-card-actions select')) {
			const card = event.target.closest('.wzl-lead-card');
			card.querySelector('.wzl-move-lead').disabled = event.target.value === card.closest('.wzl-stage').dataset.stage;
		}
	});

	board.addEventListener('click', (event) => {
		const button = event.target.closest('.wzl-move-lead');
		if (button) {
			const card = button.closest('.wzl-lead-card');
			move(card, card.querySelector('select').value, { restoreFocus: true });
		}
	});

	document.addEventListener('pointerdown', () => {
		suppressClick = false;
	}, true);

	document.addEventListener('click', (event) => {
		if (suppressClick && event.detail > 0) {
			suppressClick = false;
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	}, true);

	board.addEventListener('pointerdown', (event) => {
		const card = event.target.closest('.wzl-lead-card');
		if (event.pointerType !== 'mouse' || event.button !== 0 || !card || drag ||
			card.hasAttribute('aria-busy') || event.target.closest('.wzl-card-actions') ||
			event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
			return;
		}

		drag = {
			card,
			pointerId: event.pointerId,
			origin: card.closest('.wzl-stage'),
			startX: event.clientX,
			startY: event.clientY,
			x: event.clientX,
			y: event.clientY,
			frame: 0,
			target: null,
		};
	});

	function startDrag() {
		const { card, pointerId, origin } = drag;
		const rect = card.getBoundingClientRect();
		const preview = card.cloneNode(true);
		preview.querySelectorAll('[id]').forEach((element) => element.removeAttribute('id'));
		preview.classList.remove('is-saved');
		const layer = document.createElement('div');
		layer.className = 'webzero-leads-pipeline wzl-drag-layer';
		layer.inert = true;
		layer.setAttribute('aria-hidden', 'true');

		const list = document.createElement('ul');
		list.className = 'wzl-stage-' + origin.dataset.stage;
		list.append(preview);
		layer.append(list);
		layer.style.width = rect.width + 'px';
		document.body.append(layer);
		drag.layer = layer;
		drag.offsetX = drag.startX - rect.left;
		drag.offsetY = drag.startY - rect.top;
		card.classList.add('is-dragging');
		document.body.classList.add('wzl-is-dragging');
		card.setPointerCapture(pointerId);
		suppressClick = true;
	}

	function findTarget(x, y) {
		const stage = document.elementFromPoint(x, y)?.closest('.wzl-stage');
		return stage && board.contains(stage) && stage !== drag.origin ? stage : null;
	}

	function drawDrag() {
		drag.frame = 0;
		const { x, y, layer, offsetX, offsetY } = drag;
		layer.style.transform = 'translate3d(' + (x - offsetX) + 'px, ' + (y - offsetY) + 'px, 0)';
		const edge = 72;
		const scrollBy = y < edge ? -Math.min(22, Math.ceil((edge - y) / 4)) :
			Math.min(22, Math.max(0, Math.ceil((y - window.innerHeight + edge) / 4)));
		const scrollTop = window.scrollY;
		if (scrollBy) {
			window.scrollBy({ top: scrollBy, behavior: 'instant' });
		}

		const target = findTarget(x, y);
		if (target !== drag.target) {
			drag.target?.classList.remove('is-drop-target');
			drag.target = target;
			target?.classList.add('is-drop-target');
		}
		if (window.scrollY !== scrollTop) {
			drag.frame = requestAnimationFrame(drawDrag);
		}
	}

	document.addEventListener('pointermove', (event) => {
		if (event.pointerId !== drag?.pointerId) {
			return;
		}
		drag.x = event.clientX;
		drag.y = event.clientY;
		if (!drag.layer && Math.hypot(drag.x - drag.startX, drag.y - drag.startY) < 6) {
			return;
		}
		if (!drag.layer) {
			startDrag();
		}
		if (!drag.frame) {
			drag.frame = requestAnimationFrame(drawDrag);
		}
	});

	function finishDrag(event) {
		if (!drag || (event?.pointerId !== undefined && event.pointerId !== drag.pointerId)) {
			return;
		}

		const { card, layer, frame, target } = drag;
		const destination = layer && event?.type === 'pointerup' ? findTarget(event.clientX, event.clientY) : null;
		const dragRect = layer ? { left: drag.x - drag.offsetX, top: drag.y - drag.offsetY } : null;
		drag = null;
		if (!layer) {
			return;
		}

		cancelAnimationFrame(frame);
		layer.remove();
		card.classList.remove('is-dragging');
		target?.classList.remove('is-drop-target');
		document.body.classList.remove('wzl-is-dragging');
		if (destination) {
			move(card, destination.dataset.stage, { dragRect });
		} else {
			animatePlacement(card, dragRect);
		}
	}

	document.addEventListener('pointerup', finishDrag);
	document.addEventListener('pointercancel', finishDrag);
	board.addEventListener('lostpointercapture', finishDrag);
	window.addEventListener('blur', finishDrag);
	document.addEventListener('keydown', (event) => {
		if (event.key === 'Escape' && drag?.layer) {
			event.preventDefault();
			finishDrag();
		}
	});
})();
