<?php
	$transactionImageValue = '';
	if (!empty($row) && !empty($row->transaction_image)) {
		$transactionImageValue = trim((string)$row->transaction_image);
	}
	$transactionImageUrl = '';
	if ($transactionImageValue !== '') {
		if (preg_match('/^https?:\/\//i', $transactionImageValue)) {
			$transactionImageUrl = $transactionImageValue;
		} else {
			$transactionImageUrl = rtrim((string)ROOT, '/') . '/' . ltrim($transactionImageValue, '/');
		}
	}
?>
<style>
	.transaction-preview-card{
		position: relative;
		width: 100%;
		max-width: 240px;
		margin: 0 auto;
		border-radius: 14px;
		overflow: hidden;
		border: 1px solid rgba(229,231,235,0.25);
		background: rgba(15, 23, 42, 0.10);
		box-shadow: 0 12px 28px rgba(0,0,0,0.18);
		cursor: pointer;
		outline: none;
	}
	.transaction-preview-card:focus{
		box-shadow: 0 0 0 3px rgba(234,179,8,0.35), 0 12px 28px rgba(0,0,0,0.18);
	}
	.transaction-preview-card img{
		display:block;
		width:100%;
		height: 160px;
		object-fit: cover;
	}
	.transaction-preview-placeholder{
		height: 160px;
		display:flex;
		align-items:center;
		justify-content:center;
		flex-direction:column;
		gap:.35rem;
		border: 1px dashed rgba(234,179,8,0.45);
		border-radius: 14px;
		margin: .5rem;
		color: rgba(229,231,235,0.85);
		background: rgba(2,6,23,0.35);
	}
	.transaction-preview-placeholder .title{
		font-weight: 700;
		font-size: .9rem;
	}
	.transaction-preview-placeholder .hint{
		font-size: .78rem;
		opacity: .85;
	}
	.transaction-preview-overlay{
		position:absolute;
		inset:0;
		display:flex;
		align-items:flex-end;
		justify-content:flex-end;
		padding: .5rem;
		background: linear-gradient(180deg, rgba(2,6,23,0) 30%, rgba(2,6,23,0.75) 100%);
		opacity: 0;
		transition: opacity .18s ease;
	}
	.transaction-preview-card:hover .transaction-preview-overlay{
		opacity: 1;
	}
	.transaction-preview-badge{
		display:inline-flex;
		align-items:center;
		gap:.35rem;
		font-size: .8rem;
		font-weight: 600;
		padding: .3rem .6rem;
		border-radius: 999px;
		background: rgba(234,179,8,0.92);
		color: #111827;
	}
	.visually-hidden-file{
		position:absolute !important;
		width:1px !important;
		height:1px !important;
		padding:0 !important;
		margin:-1px !important;
		overflow:hidden !important;
		clip: rect(0,0,0,0) !important;
		white-space:nowrap !important;
		border:0 !important;
	}
</style>

<div class="col-md-12 transaction_image">
	<label for="transaction_image" class="form-label">Transaction image <span class="text-danger">*</span></label>
	<div class="mt-2" id="transactionImagePreviewWrap">
		<div class="transaction-preview-card" id="transactionImagePreviewCard" role="button" tabindex="0">
			<div class="transaction-preview-placeholder" id="transactionImagePreviewPlaceholder">
				<i class="fa-solid fa-cloud-arrow-up" style="font-size:1.4rem;"></i>
				<div class="title">Upload transaction picture</div>
				<div class="hint">Tap to choose an image</div>
			</div>
			<img id="transactionImagePreview" src="<?=esc($transactionImageUrl)?>" alt="Transaction picture" style="display:none;">
			<div class="transaction-preview-overlay">
				<span class="transaction-preview-badge"><i class="fa-solid fa-camera"></i> Change</span>
			</div>
		</div>
		<small class="text-muted d-block mt-1 text-center">Tap the preview to choose or change the image</small>
	</div>
	<small class="text-danger js-transaction-image-error text-center d-block"><?=(string)($errors['transaction_image'] ?? '')?></small>
	<input name="transaction_image" type="file" class="visually-hidden-file" id="transaction_image" accept="image/*" data-existing-image="<?=$transactionImageUrl !== '' ? '1' : '0'?>">
</div>

<script>
	<?=get_app_image_cropper_inline_source()?>
	(function(){
		const formEl = document.querySelector('form[method="post"]');
		if(!formEl) return;

		function bindSimplePreviewCard(){
			const inputEl = document.getElementById('transaction_image');
			const cardEl = document.getElementById('transactionImagePreviewCard');
			const previewEl = document.getElementById('transactionImagePreview');
			const placeholderEl = document.getElementById('transactionImagePreviewPlaceholder');
			if(!inputEl || !cardEl || !previewEl || !placeholderEl || cardEl.dataset.simplePreviewBound === '1'){
				return;
			}

			cardEl.dataset.simplePreviewBound = '1';
			const openPicker = function(event){
				if(event){
					event.preventDefault();
				}
				inputEl.click();
			};

			cardEl.addEventListener('click', openPicker);
			cardEl.addEventListener('keydown', function(event){
				if(event.key === 'Enter' || event.key === ' '){
					openPicker(event);
				}
			});

			inputEl.addEventListener('change', function(){
				const file = inputEl.files && inputEl.files[0] ? inputEl.files[0] : null;
				if(!file){
					return;
				}
				const reader = new FileReader();
				reader.onload = function(loadEvent){
					previewEl.src = loadEvent.target && loadEvent.target.result ? loadEvent.target.result : '';
					previewEl.style.display = 'block';
					placeholderEl.style.display = 'none';
				};
				reader.readAsDataURL(file);
			});
		}

		function bindCropperCard(){
			if(!window.AppImageCropper || typeof window.AppImageCropper.bindPreviewCard !== 'function'){
				bindSimplePreviewCard();
				return;
			}

			window.AppImageCropper.bindPreviewCard({
				formEl: formEl,
				inputId: 'transaction_image',
				cardId: 'transactionImagePreviewCard',
				previewId: 'transactionImagePreview',
				placeholderId: 'transactionImagePreviewPlaceholder',
				errorSelector: '.js-transaction-image-error',
				aspectRatio: 1.5,
				maxBytes: window.AppImageCropper.MAX_BYTES,
				modalTitle: 'Crop transaction picture',
				required: true,
				requiredMessage: 'Transaction image is required.'
			});
		}

		bindCropperCard();
		document.addEventListener('app-image-cropper-ready', bindCropperCard);
	})();
</script>
