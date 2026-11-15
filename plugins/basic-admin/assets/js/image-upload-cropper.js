(function(window, document){
	'use strict';

	if (window.AppImageCropper) {
		return;
	}

	var MAX_BYTES = 1024 * 1024;
	var modalRefs = null;
	var activeTask = null;

	function resolveElement(value) {
		if (!value) {
			return null;
		}
		if (typeof value === 'string') {
			return document.querySelector(value) || document.getElementById(value.replace(/^#/, ''));
		}
		return value;
	}

	function clamp(value, min, max) {
		return Math.min(max, Math.max(min, value));
	}

	function ensureStyles() {
		if (document.getElementById('appImageCropperStyles')) {
			return;
		}

		var styleEl = document.createElement('style');
		styleEl.id = 'appImageCropperStyles';
		styleEl.textContent = [
			'.aic-modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;padding:1.25rem;background:rgba(2,6,23,.74);z-index:2000;overflow-y:auto;-webkit-overflow-scrolling:touch;}',
			'.aic-modal.is-open{display:flex;}',
			'.aic-dialog{width:min(92vw,760px);max-height:calc(100vh - 2.5rem);margin:auto 0;display:flex;flex-direction:column;overflow:hidden;border-radius:14px;background:#111827;color:#f9fafb;box-shadow:0 24px 80px rgba(0,0,0,.42);}',
			'.aic-header,.aic-actions{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.1rem;border-bottom:1px solid rgba(255,255,255,.08);gap:1rem;flex:0 0 auto;background:#111827;}',
			'.aic-actions{border-bottom:0;border-top:1px solid rgba(255,255,255,.08);justify-content:flex-end;}',
			'.aic-title{font-size:1rem;font-weight:700;}',
			'.aic-close{border:0;background:transparent;color:#e5e7eb;font-size:1.6rem;line-height:1;cursor:pointer;padding:0 .2rem;}',
			'.aic-body{flex:1 1 auto;min-height:0;overflow-y:auto;padding:1rem 1.1rem 1.1rem;}',
			'.aic-stage-wrap{display:flex;justify-content:center;align-items:center;}',
			'.aic-full-preview{display:none;max-width:100%;max-height:min(55vh,460px);object-fit:contain;border-radius:12px;}',
			'.aic-full-preview.is-visible{display:block;}',
			'.aic-stage{position:relative;display:flex;align-items:center;justify-content:center;width:100%;min-height:280px;border-radius:12px;background:linear-gradient(135deg, rgba(15,23,42,.95), rgba(30,41,59,.92));overflow:hidden;}',
			'.aic-cropbox{position:relative;border-radius:12px;overflow:hidden;box-shadow:0 0 0 9999px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.25) inset;}',
			'.aic-cropbox::after{content:"";position:absolute;inset:0;border:1px solid rgba(255,255,255,.18);pointer-events:none;}',
			'.aic-image{position:absolute;top:0;left:0;max-width:none;user-select:none;-webkit-user-drag:none;touch-action:none;cursor:grab;transform-origin:0 0;}',
			'.aic-image.is-dragging{cursor:grabbing;}',
			'.aic-toolbar{display:grid;gap:.85rem;margin-top:1rem;}',
			'.aic-slider-row{display:flex;align-items:center;gap:.9rem;flex-wrap:wrap;}',
			'.aic-slider-row input[type="range"]{flex:1 1 220px;}',
			'.aic-meta{display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;font-size:.9rem;color:#cbd5e1;}',
			'.aic-status{min-height:1.25rem;font-size:.84rem;color:#fbbf24;}',
			'body.aic-modal-open{overflow:hidden;}'
		].join('');
		document.head.appendChild(styleEl);
	}

	function ensureModal() {
		if (modalRefs) {
			return modalRefs;
		}

		ensureStyles();

		var root = document.createElement('div');
		root.className = 'aic-modal';
		root.innerHTML = '' +
			'<div class="aic-dialog" role="dialog" aria-modal="true" aria-labelledby="aicTitle">' +
				'<div class="aic-header">' +
					'<div class="aic-title" id="aicTitle">Crop image</div>' +
					'<button type="button" class="aic-close" aria-label="Close">&times;</button>' +
				'</div>' +
				'<div class="aic-body">' +
					'<div class="aic-stage-wrap"><img class="aic-full-preview" alt="Full selected image"><div class="aic-stage"><div class="aic-cropbox"><img class="aic-image" alt="Selected image"></div></div></div>' +
					'<div class="aic-toolbar">' +
						'<div class="aic-slider-row"><span>Zoom</span><input type="range" min="100" max="300" step="1" value="100"><span class="aic-zoom-label">100%</span></div>' +
						'<div class="aic-meta"><span class="aic-instructions">Drag the image to position it inside the frame.</span><span class="aic-limit-label">Max 1MB</span></div>' +
						'<div class="aic-status"></div>' +
					'</div>' +
				'</div>' +
				'<div class="aic-actions">' +
					'<button type="button" class="btn btn-outline-light aic-cancel">Cancel</button>' +
					'<button type="button" class="btn btn-outline-light aic-start-crop">Crop image</button>' +
					'<button type="button" class="btn btn-primary aic-use-full">Use full image</button>' +
					'<button type="button" class="btn btn-primary aic-apply">Use image</button>' +
				'</div>' +
			'</div>';
		document.body.appendChild(root);

		modalRefs = {
			root: root,
			title: root.querySelector('.aic-title'),
			fullPreview: root.querySelector('.aic-full-preview'),
			stage: root.querySelector('.aic-stage'),
			cropbox: root.querySelector('.aic-cropbox'),
			image: root.querySelector('.aic-image'),
			instructions: root.querySelector('.aic-instructions'),
			zoom: root.querySelector('input[type="range"]'),
			zoomLabel: root.querySelector('.aic-zoom-label'),
			limitLabel: root.querySelector('.aic-limit-label'),
			status: root.querySelector('.aic-status'),
			close: root.querySelector('.aic-close'),
			cancel: root.querySelector('.aic-cancel'),
			startCrop: root.querySelector('.aic-start-crop'),
			useFull: root.querySelector('.aic-use-full'),
			apply: root.querySelector('.aic-apply')
		};

		function cancelActive() {
			if (!activeTask) {
				return;
			}
			closeModal({ ok: false, cancelled: true });
		}

		modalRefs.close.addEventListener('click', cancelActive);
		modalRefs.cancel.addEventListener('click', cancelActive);
		modalRefs.root.addEventListener('click', function(event){
			if (event.target === modalRefs.root) {
				cancelActive();
			}
		});

		document.addEventListener('keydown', function(event){
			if (event.key === 'Escape' && activeTask) {
				cancelActive();
			}
		});

		modalRefs.zoom.addEventListener('input', function(){
			if (!activeTask) {
				return;
			}
			var nextScale = activeTask.minScale * (parseInt(modalRefs.zoom.value, 10) / 100);
			setScale(nextScale, activeTask.viewportWidth / 2, activeTask.viewportHeight / 2);
		});

		modalRefs.stage.addEventListener('pointerdown', function(event){
			if (!activeTask) {
				return;
			}
			activeTask.drag = {
				pointerId: event.pointerId,
				startX: event.clientX,
				startY: event.clientY,
				originX: activeTask.posX,
				originY: activeTask.posY
			};
			modalRefs.image.classList.add('is-dragging');
			if (modalRefs.stage.setPointerCapture) {
				modalRefs.stage.setPointerCapture(event.pointerId);
			}
			event.preventDefault();
		});

		modalRefs.stage.addEventListener('pointermove', function(event){
			if (!activeTask || !activeTask.drag || event.pointerId !== activeTask.drag.pointerId) {
				return;
			}
			activeTask.posX = activeTask.drag.originX + (event.clientX - activeTask.drag.startX);
			activeTask.posY = activeTask.drag.originY + (event.clientY - activeTask.drag.startY);
			clampPosition();
			renderActive();
		});

		function releasePointer(event) {
			if (!activeTask || !activeTask.drag || event.pointerId !== activeTask.drag.pointerId) {
				return;
			}
			activeTask.drag = null;
			modalRefs.image.classList.remove('is-dragging');
		}

		modalRefs.stage.addEventListener('pointerup', releasePointer);
		modalRefs.stage.addEventListener('pointercancel', releasePointer);
		modalRefs.stage.addEventListener('wheel', function(event){
			if (!activeTask) {
				return;
			}
			event.preventDefault();
			var delta = event.deltaY < 0 ? 1.06 : 0.94;
			setScale(activeTask.scale * delta, event.offsetX, event.offsetY);
		}, { passive: false });

		modalRefs.apply.addEventListener('click', function(){
			finishImageSelection(buildCroppedResult, 'Preparing cropped image...', 'Unable to crop this image.');
		});

		modalRefs.startCrop.addEventListener('click', function(){
			if (!activeTask || activeTask.busy) {
				return;
			}
			activeTask.viewMode = 'crop';
			updateModalView();
			resizeViewport(false);
		});

		modalRefs.useFull.addEventListener('click', function(){
			finishImageSelection(buildFullImageResult, 'Preparing full image...', 'Unable to prepare this image.');
		});

		window.addEventListener('resize', function(){
			if (!activeTask) {
				return;
			}
			resizeViewport(true);
		});

		return modalRefs;
	}

	function formatLimitText(bytes) {
		return 'Max ' + Math.round(bytes / 1024 / 1024) + 'MB';
	}

	function openModal(task) {
		var refs = ensureModal();
		activeTask = task;
		refs.title.textContent = task.title || 'Crop image';
		refs.fullPreview.src = task.imageUrl;
		refs.status.textContent = '';
		refs.limitLabel.textContent = formatLimitText(task.maxBytes);
		refs.zoom.value = '100';
		refs.apply.disabled = false;
		refs.startCrop.disabled = false;
		refs.useFull.disabled = false;
		refs.cancel.disabled = false;
		task.viewMode = task.allowUncropped ? 'full' : 'crop';
		updateModalView();
		resizeViewport(false);
		refs.root.classList.add('is-open');
		document.body.classList.add('aic-modal-open');
	}

	function updateModalView() {
		if (!activeTask) {
			return;
		}
		var refs = ensureModal();
		var showFullPreview = activeTask.allowUncropped && activeTask.viewMode === 'full';
		refs.fullPreview.classList.toggle('is-visible', showFullPreview);
		refs.stage.style.display = showFullPreview ? 'none' : '';
		refs.zoom.parentNode.style.display = showFullPreview ? 'none' : '';
		refs.instructions.textContent = showFullPreview
			? 'This preview shows the complete image without cropping.'
			: 'Drag the image to position it inside the frame.';
		refs.startCrop.style.display = showFullPreview ? '' : 'none';
		refs.useFull.style.display = showFullPreview ? '' : 'none';
		refs.apply.style.display = showFullPreview ? 'none' : '';
		refs.apply.textContent = activeTask.allowUncropped ? 'Crop and use image' : 'Use image';
	}

	function finishImageSelection(buildResult, progressMessage, errorMessage) {
		if (!activeTask || activeTask.busy) {
			return;
		}
		activeTask.busy = true;
		modalRefs.apply.disabled = true;
		modalRefs.startCrop.disabled = true;
		modalRefs.useFull.disabled = true;
		modalRefs.cancel.disabled = true;
		modalRefs.status.textContent = progressMessage;
		buildResult(activeTask).then(function(result){
			closeModal(result);
		}).catch(function(error){
			if (!activeTask) {
				return;
			}
			activeTask.busy = false;
			modalRefs.apply.disabled = false;
			modalRefs.startCrop.disabled = false;
			modalRefs.useFull.disabled = false;
			modalRefs.cancel.disabled = false;
			modalRefs.status.textContent = error && error.message ? error.message : errorMessage;
		});
	}

	function closeModal(result) {
		var refs = ensureModal();
		refs.root.classList.remove('is-open');
		document.body.classList.remove('aic-modal-open');
		refs.fullPreview.removeAttribute('src');
		if (activeTask && activeTask.imageUrl) {
			URL.revokeObjectURL(activeTask.imageUrl);
		}
		var resolver = activeTask ? activeTask.resolve : null;
		activeTask = null;
		if (resolver) {
			resolver(result);
		}
	}

	function resizeViewport(keepCenter) {
		if (!activeTask) {
			return;
		}

		var centerX = 0;
		var centerY = 0;
		if (keepCenter) {
			centerX = (activeTask.viewportWidth / 2 - activeTask.posX) / activeTask.scale;
			centerY = (activeTask.viewportHeight / 2 - activeTask.posY) / activeTask.scale;
		}

		var aspectRatio = activeTask.aspectRatio > 0 ? activeTask.aspectRatio : (activeTask.image.naturalWidth / activeTask.image.naturalHeight);
		var maxWidth = Math.min(window.innerWidth - 100, 620);
		var maxHeight = Math.min(window.innerHeight - 290, 460);
		var width = maxWidth;
		var height = width / aspectRatio;

		if (height > maxHeight) {
			height = maxHeight;
			width = height * aspectRatio;
		}

		activeTask.viewportWidth = Math.max(220, Math.round(width));
		activeTask.viewportHeight = Math.max(160, Math.round(height));
		activeTask.minScale = Math.max(
			activeTask.viewportWidth / activeTask.image.naturalWidth,
			activeTask.viewportHeight / activeTask.image.naturalHeight
		);
		activeTask.maxScale = activeTask.minScale * 4;
		activeTask.scale = Math.max(activeTask.minScale, Math.min(activeTask.scale || activeTask.minScale, activeTask.maxScale));

		if (keepCenter) {
			activeTask.posX = activeTask.viewportWidth / 2 - (centerX * activeTask.scale);
			activeTask.posY = activeTask.viewportHeight / 2 - (centerY * activeTask.scale);
		} else {
			activeTask.posX = (activeTask.viewportWidth - (activeTask.image.naturalWidth * activeTask.scale)) / 2;
			activeTask.posY = (activeTask.viewportHeight - (activeTask.image.naturalHeight * activeTask.scale)) / 2;
		}

		clampPosition();
		renderActive();
	}

	function clampPosition() {
		if (!activeTask) {
			return;
		}
		var minX = activeTask.viewportWidth - (activeTask.image.naturalWidth * activeTask.scale);
		var minY = activeTask.viewportHeight - (activeTask.image.naturalHeight * activeTask.scale);
		activeTask.posX = clamp(activeTask.posX, Math.min(0, minX), 0);
		activeTask.posY = clamp(activeTask.posY, Math.min(0, minY), 0);
	}

	function setScale(nextScale, anchorX, anchorY) {
		if (!activeTask) {
			return;
		}
		var safeScale = clamp(nextScale, activeTask.minScale, activeTask.maxScale);
		var imageX = (anchorX - activeTask.posX) / activeTask.scale;
		var imageY = (anchorY - activeTask.posY) / activeTask.scale;
		activeTask.scale = safeScale;
		activeTask.posX = anchorX - (imageX * activeTask.scale);
		activeTask.posY = anchorY - (imageY * activeTask.scale);
		clampPosition();
		renderActive();
	}

	function renderActive() {
		if (!activeTask) {
			return;
		}
		var refs = ensureModal();
		refs.cropbox.style.width = activeTask.viewportWidth + 'px';
		refs.cropbox.style.height = activeTask.viewportHeight + 'px';
		refs.image.src = activeTask.image.src;
		refs.image.style.width = activeTask.image.naturalWidth + 'px';
		refs.image.style.height = activeTask.image.naturalHeight + 'px';
		refs.image.style.transform = 'translate(' + activeTask.posX + 'px,' + activeTask.posY + 'px) scale(' + activeTask.scale + ')';
		refs.zoom.value = String(Math.round((activeTask.scale / activeTask.minScale) * 100));
		refs.zoomLabel.textContent = refs.zoom.value + '%';
	}

	function loadImage(file) {
		return new Promise(function(resolve, reject){
			var imageUrl = URL.createObjectURL(file);
			var image = new Image();
			image.onload = function(){
				resolve({ image: image, imageUrl: imageUrl });
			};
			image.onerror = function(){
				URL.revokeObjectURL(imageUrl);
				reject(new Error('Unable to open the selected image.'));
			};
			image.src = imageUrl;
		});
	}

	function createCanvas(width, height) {
		var canvas = document.createElement('canvas');
		canvas.width = width;
		canvas.height = height;
		return canvas;
	}

	function canvasToBlob(canvas, type, quality) {
		return new Promise(function(resolve, reject){
			canvas.toBlob(function(blob){
				if (blob) {
					resolve(blob);
					return;
				}
				reject(new Error('Unable to encode the image.'));
			}, type, quality);
		});
	}

	function scaleCanvas(sourceCanvas, scaleRatio) {
		if (scaleRatio >= 0.999) {
			return sourceCanvas;
		}
		var nextCanvas = createCanvas(
			Math.max(1, Math.round(sourceCanvas.width * scaleRatio)),
			Math.max(1, Math.round(sourceCanvas.height * scaleRatio))
		);
		var context = nextCanvas.getContext('2d');
		context.imageSmoothingEnabled = true;
		context.imageSmoothingQuality = 'high';
		context.drawImage(sourceCanvas, 0, 0, nextCanvas.width, nextCanvas.height);
		return nextCanvas;
	}

	function uniqueTypes(types) {
		var seen = {};
		return types.filter(function(type){
			if (!type || seen[type]) {
				return false;
			}
			seen[type] = true;
			return true;
		});
	}

	async function exportCanvasWithinLimit(canvas, preferredType, maxBytes) {
		var orderedTypes = uniqueTypes([preferredType, 'image/jpeg', 'image/webp', 'image/png']);
		for (var typeIndex = 0; typeIndex < orderedTypes.length; typeIndex++) {
			var type = orderedTypes[typeIndex];
			var workingCanvas = canvas;
			var quality = type === 'image/png' ? undefined : 0.92;

			for (var attempt = 0; attempt < 8; attempt++) {
				var blob = await canvasToBlob(workingCanvas, type, quality);
				if (blob.size <= maxBytes) {
					return { blob: blob, type: type };
				}

				if (workingCanvas.width <= 320 || workingCanvas.height <= 320) {
					break;
				}

				if (type === 'image/png') {
					workingCanvas = scaleCanvas(workingCanvas, 0.82);
					continue;
				}

				if (quality > 0.62) {
					quality = Math.max(0.58, quality - 0.08);
				} else {
					workingCanvas = scaleCanvas(workingCanvas, 0.85);
				}
			}
		}

		throw new Error('Unable to reduce this image below 1MB. Please choose a smaller image.');
	}

	function buildFileName(originalName, outputType) {
		var baseName = String(originalName || 'image').replace(/\.[^.]+$/, '');
		var extension = 'jpg';
		if (outputType === 'image/png') {
			extension = 'png';
		} else if (outputType === 'image/webp') {
			extension = 'webp';
		}
		return baseName + '.' + extension;
	}

	async function buildCroppedResult(task) {
		var cropX = (-task.posX) / task.scale;
		var cropY = (-task.posY) / task.scale;
		var cropWidth = task.viewportWidth / task.scale;
		var cropHeight = task.viewportHeight / task.scale;
		var maxDimension = 1800;
		var resizeFactor = Math.min(1, maxDimension / Math.max(cropWidth, cropHeight));
		var outputWidth = Math.max(1, Math.round(cropWidth * resizeFactor));
		var outputHeight = Math.max(1, Math.round(cropHeight * resizeFactor));
		var preferredType = task.outputType || (task.file.type && /^image\/(png|webp|jpeg|jpg)$/i.test(task.file.type) ? task.file.type.replace('jpg', 'jpeg') : 'image/jpeg');
		var canvas = createCanvas(outputWidth, outputHeight);
		var context = canvas.getContext('2d');
		context.imageSmoothingEnabled = true;
		context.imageSmoothingQuality = 'high';
		if (preferredType === 'image/jpeg') {
			context.fillStyle = '#ffffff';
			context.fillRect(0, 0, outputWidth, outputHeight);
		}
		context.drawImage(task.image, cropX, cropY, cropWidth, cropHeight, 0, 0, outputWidth, outputHeight);

		var exported = await exportCanvasWithinLimit(canvas, preferredType, task.maxBytes);
		var outputFile = new File([exported.blob], buildFileName(task.file.name, exported.type), {
			type: exported.type,
			lastModified: Date.now()
		});
		return {
			ok: true,
			file: outputFile,
			blob: exported.blob,
			previewUrl: URL.createObjectURL(exported.blob),
			type: exported.type
		};
	}

	async function buildFullImageResult(task) {
		var maxDimension = 1800;
		var resizeFactor = Math.min(1, maxDimension / Math.max(task.image.naturalWidth, task.image.naturalHeight));
		var outputWidth = Math.max(1, Math.round(task.image.naturalWidth * resizeFactor));
		var outputHeight = Math.max(1, Math.round(task.image.naturalHeight * resizeFactor));
		var preferredType = task.outputType || (task.file.type && /^image\/(png|webp|jpeg|jpg)$/i.test(task.file.type) ? task.file.type.replace('jpg', 'jpeg') : 'image/jpeg');
		var canvas = createCanvas(outputWidth, outputHeight);
		var context = canvas.getContext('2d');
		context.imageSmoothingEnabled = true;
		context.imageSmoothingQuality = 'high';
		if (preferredType === 'image/jpeg') {
			context.fillStyle = '#ffffff';
			context.fillRect(0, 0, outputWidth, outputHeight);
		}
		context.drawImage(task.image, 0, 0, outputWidth, outputHeight);

		var exported = await exportCanvasWithinLimit(canvas, preferredType, task.maxBytes);
		var outputFile = new File([exported.blob], buildFileName(task.file.name, exported.type), {
			type: exported.type,
			lastModified: Date.now()
		});
		return {
			ok: true,
			file: outputFile,
			blob: exported.blob,
			previewUrl: URL.createObjectURL(exported.blob),
			type: exported.type
		};
	}

	function setInputFile(inputEl, file) {
		var transfer = new DataTransfer();
		transfer.items.add(file);
		inputEl.files = transfer.files;
	}

	function setPreview(previewEl, placeholderEl, src, isObjectUrl) {
		if (!previewEl) {
			return;
		}
		var previousUrl = previewEl.dataset.objectUrl || '';
		if (previousUrl && previousUrl !== src) {
			URL.revokeObjectURL(previousUrl);
			delete previewEl.dataset.objectUrl;
		}
		if (!src) {
			previewEl.removeAttribute('src');
			previewEl.style.display = 'none';
			if (placeholderEl) {
				placeholderEl.style.display = 'flex';
			}
			return;
		}
		previewEl.src = src;
		previewEl.style.display = 'block';
		if (placeholderEl) {
			placeholderEl.style.display = 'none';
		}
		if (isObjectUrl) {
			previewEl.dataset.objectUrl = src;
		}
	}

	function setErrorText(errorEl, message) {
		if (errorEl) {
			errorEl.textContent = message || '';
		}
	}

	function showMessage(options, message) {
		setErrorText(options.errorEl, message);
		if (!message) {
			return;
		}
		if (options.useSwal && window.Swal && typeof window.Swal.fire === 'function') {
			window.Swal.fire({
				icon: 'error',
				title: options.errorTitle || 'Invalid image',
				text: message
			});
			return;
		}
		if (options.useAlert) {
			window.alert(message);
		}
	}

	async function processInputSelection(inputEl, options) {
		options = options || {};
		var previewEl = resolveElement(options.previewEl);
		var placeholderEl = resolveElement(options.placeholderEl);
		var errorEl = resolveElement(options.errorEl || options.errorSelector);
		var file = options.file || ((inputEl.files && inputEl.files[0]) ? inputEl.files[0] : null);
		var currentSrc = previewEl ? String(previewEl.getAttribute('src') || '').trim() : '';

		options.errorEl = errorEl;
		setErrorText(errorEl, '');

		if (!file) {
			if (!currentSrc) {
				setPreview(previewEl, placeholderEl, '', false);
			}
			return { ok: false, cancelled: true };
		}

		if (!file.type || !/^image\//i.test(file.type)) {
			inputEl.value = '';
			showMessage(options, options.invalidTypeMessage || 'Invalid file type. Please select an image.');
			if (!currentSrc) {
				setPreview(previewEl, placeholderEl, '', false);
			}
			return { ok: false, invalid: true };
		}

		var loaded = await loadImage(file);
		var result = await new Promise(function(resolve){
			openModal({
				resolve: resolve,
				file: file,
				image: loaded.image,
				imageUrl: loaded.imageUrl,
				title: options.modalTitle || 'Crop image',
				allowUncropped: !!options.allowUncropped,
				aspectRatio: options.aspectRatio || 1,
				outputType: options.outputType || '',
				maxBytes: options.maxBytes || MAX_BYTES,
				scale: 0,
				minScale: 0,
				maxScale: 0,
				posX: 0,
				posY: 0,
				viewportWidth: 0,
				viewportHeight: 0,
				drag: null,
				busy: false
			});
		});

		if (!result || !result.ok) {
			inputEl.value = '';
			if (!currentSrc) {
				setPreview(previewEl, placeholderEl, '', false);
			}
			return result || { ok: false, cancelled: true };
		}

		setInputFile(inputEl, result.file);
		setPreview(previewEl, placeholderEl, result.previewUrl, true);
		setErrorText(errorEl, '');
		if (typeof options.onSuccess === 'function') {
			options.onSuccess(result);
		}
		return result;
	}

	function bindPreviewCard(options) {
		options = options || {};
		var inputEl = resolveElement(options.inputEl || options.inputId);
		var cardEl = resolveElement(options.cardEl || options.cardId);
		var previewEl = resolveElement(options.previewEl || options.previewId);
		var placeholderEl = resolveElement(options.placeholderEl || options.placeholderId);
		var errorEl = resolveElement(options.errorEl || options.errorSelector);
		var formEl = resolveElement(options.formEl) || (inputEl ? inputEl.form : null);

		if (!inputEl || !cardEl || !previewEl || !placeholderEl) {
			return null;
		}

		if (String(previewEl.getAttribute('src') || '').trim() !== '') {
			previewEl.style.display = 'block';
			placeholderEl.style.display = 'none';
		}

		cardEl.addEventListener('click', function(){
			inputEl.click();
		});

		cardEl.addEventListener('keydown', function(event){
			if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				inputEl.click();
			}
		});

		inputEl.addEventListener('change', function(){
			processInputSelection(inputEl, {
				previewEl: previewEl,
				placeholderEl: placeholderEl,
				errorEl: errorEl,
				aspectRatio: options.aspectRatio || 1,
				maxBytes: options.maxBytes || MAX_BYTES,
				outputType: options.outputType || '',
				modalTitle: options.modalTitle || 'Crop image',
				allowUncropped: !!options.allowUncropped,
				onSuccess: options.onSuccess,
				errorTitle: options.errorTitle || 'Invalid image',
				useSwal: !!options.useSwal,
				useAlert: !!options.useAlert
			}).then(function(result){
				if (typeof options.onComplete === 'function') {
					options.onComplete(result || { ok: false });
				}
			}).catch(function(error){
				inputEl.value = '';
				showMessage({ errorEl: errorEl, useSwal: !!options.useSwal, useAlert: !!options.useAlert, errorTitle: options.errorTitle || 'Invalid image' }, error.message || 'Unable to process this image.');
				if (typeof options.onComplete === 'function') {
					options.onComplete({ ok: false, error: error.message || 'Unable to process this image.' });
				}
			});
		});

		if (options.required && formEl) {
			formEl.addEventListener('submit', function(event){
				var hasFile = !!(inputEl.files && inputEl.files.length > 0);
				var hasPreview = String(previewEl.getAttribute('src') || '').trim() !== '';
				if (!hasFile && !hasPreview) {
					event.preventDefault();
					showMessage({
						errorEl: errorEl,
						useSwal: !!options.useSwal,
						useAlert: !!options.useAlert,
						errorTitle: options.requiredTitle || 'Image required'
					}, options.requiredMessage || 'Please upload an image.');
					cardEl.focus();
				}
			});
		}

		return {
			inputEl: inputEl,
			cardEl: cardEl,
			previewEl: previewEl,
			placeholderEl: placeholderEl,
			errorEl: errorEl
		};
	}

	function handleLegacyChange(event, options) {
		options = options || {};
		var inputEl = event && event.currentTarget ? event.currentTarget : resolveElement(options.inputEl);
		if (!inputEl) {
			return Promise.resolve({ ok: false, cancelled: true });
		}

		var previewEl = resolveElement(options.previewEl);
		if (!previewEl) {
			previewEl = inputEl.parentNode ? inputEl.parentNode.querySelector(options.previewSelector || 'img') : null;
		}

		return processInputSelection(inputEl, {
			previewEl: previewEl,
			placeholderEl: options.placeholderEl || null,
			errorEl: options.errorEl || null,
			aspectRatio: options.aspectRatio || 1,
			maxBytes: options.maxBytes || MAX_BYTES,
			outputType: options.outputType || '',
			modalTitle: options.modalTitle || 'Crop image',
			allowUncropped: !!options.allowUncropped,
			errorTitle: options.errorTitle || 'Invalid image',
			useSwal: !!options.useSwal,
			useAlert: !!options.useAlert,
			onSuccess: options.onSuccess
		}).catch(function(error){
			showMessage({
				errorEl: options.errorEl || null,
				useSwal: !!options.useSwal,
				useAlert: !!options.useAlert,
				errorTitle: options.errorTitle || 'Invalid image'
			}, error.message || 'Unable to process this image.');
			return { ok: false, error: error.message || 'Unable to process this image.' };
		});
	}

	window.AppImageCropper = {
		MAX_BYTES: MAX_BYTES,
		bindPreviewCard: bindPreviewCard,
		handleLegacyChange: handleLegacyChange
	};
})(window, document);
