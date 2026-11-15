<?php
/**
 * @var string $admin_route
 * @var string $plugin_route
 * @var int $user_id
 * @var array $errors
 * @var array $users
 * @var array $onlineUserIds
 * @var array $onlineUserRows
 * @var array $subjectOptions
 */
?>
<?php if(user_can('add_tickets')): ?>
	<style>
		.ticket-create-wrap {
			max-width: 920px;
			margin: 1.5rem auto;
		}
		.ticket-create-card {
			overflow: hidden;
			border: 1px solid var(--bs-border-color);
			border-radius: 1rem;
			background: var(--bs-body-bg);
			box-shadow: 0 1rem 2.5rem rgba(20, 35, 60, .08);
		}
		.ticket-create-heading {
			display: flex;
			align-items: center;
			gap: 1rem;
			padding: clamp(1.25rem, 4vw, 2rem);
			border-bottom: 1px solid var(--bs-border-color);
			background: linear-gradient(120deg, rgba(13, 110, 253, .1), rgba(111, 66, 193, .06));
		}
		.ticket-create-heading-icon {
			display: grid;
			width: 3.25rem;
			height: 3.25rem;
			flex: 0 0 auto;
			place-items: center;
			border-radius: 1rem;
			background: var(--bs-primary);
			color: #fff;
			font-size: 1.3rem;
			box-shadow: 0 .5rem 1.25rem rgba(13, 110, 253, .22);
		}
		.ticket-create-heading h1 {
			margin: 0 0 .25rem;
			font-size: clamp(1.25rem, 3vw, 1.65rem);
			font-weight: 700;
			letter-spacing: -.025em;
		}
		.ticket-create-heading p {
			margin: 0;
			color: var(--bs-secondary-color);
		}
		.ticket-create-body {
			padding: clamp(1rem, 4vw, 2rem);
		}
		.ticket-create-form .form-label {
			margin-bottom: .45rem;
			font-size: .875rem;
			font-weight: 650;
			color: var(--bs-body-color);
		}
		.ticket-create-form .form-control,
		.ticket-create-form .form-select {
			min-height: 2.8rem;
			border-color: var(--bs-border-color);
			border-radius: .65rem;
			background-color: var(--bs-tertiary-bg);
		}
		.ticket-create-form .form-control:focus,
		.ticket-create-form .form-select:focus {
			border-color: rgba(13, 110, 253, .65);
			box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .13);
		}
		.ticket-create-online {
			padding: .8rem 1rem;
			border: 1px solid rgba(13, 110, 253, .14);
			border-radius: .75rem;
			background: rgba(13, 110, 253, .045);
		}
		.ticket-create-actions {
			display: flex;
			justify-content: space-between;
			gap: .75rem;
			padding-top: 1rem;
			border-top: 1px solid var(--bs-border-color);
		}
		.ticket-online-dot{
			display:inline-block;
			width:8px;
			height:8px;
			border-radius:50%;
			background:#198754;
			margin-right:4px;
		}
		.ticket-create-wrap .note-editor.note-frame,
		.ticket-create-wrap .note-editor.note-airframe {
			background: var(--bs-body-bg, #fff);
			color: var(--bs-body-color, #212529);
			border: 1px solid var(--bs-border-color, #dee2e6);
			border-radius: .65rem;
			overflow: hidden;
		}
		.ticket-create-wrap .note-editor .note-editing-area,
		.ticket-create-wrap .note-editor .note-editing-area .note-editable {
			background: var(--bs-body-bg, #fff) !important;
			color: var(--bs-body-color, #212529) !important;
		}
		.ticket-create-wrap .note-editor .note-editing-area .note-editable *,
		.ticket-create-wrap .note-editor .note-editing-area .note-editable p,
		.ticket-create-wrap .note-editor .note-editing-area .note-editable div {
			color: inherit !important;
		}
		.ticket-create-wrap .note-editor .note-placeholder {
			color: var(--bs-secondary-color, #6c757d) !important;
		}
		[data-bs-theme="dark"] .ticket-create-wrap .note-editor.note-frame,
		[data-bs-theme="dark"] .ticket-create-wrap .note-editor.note-airframe,
		body.dark .ticket-create-wrap .note-editor.note-frame,
		body.dark .ticket-create-wrap .note-editor.note-airframe,
		.dark-mode .ticket-create-wrap .note-editor.note-frame,
		.dark-mode .ticket-create-wrap .note-editor.note-airframe {
			background: #212529;
			color: #f8f9fa;
			border-color: #495057;
		}
		[data-bs-theme="dark"] .ticket-create-wrap .note-editor .note-editing-area,
		[data-bs-theme="dark"] .ticket-create-wrap .note-editor .note-editing-area .note-editable,
		body.dark .ticket-create-wrap .note-editor .note-editing-area,
		body.dark .ticket-create-wrap .note-editor .note-editing-area .note-editable,
		.dark-mode .ticket-create-wrap .note-editor .note-editing-area,
		.dark-mode .ticket-create-wrap .note-editor .note-editing-area .note-editable {
			background: #212529 !important;
			color: #f8f9fa !important;
		}
		[data-bs-theme="dark"] .ticket-create-wrap .note-editor .note-placeholder,
		body.dark .ticket-create-wrap .note-editor .note-placeholder,
		.dark-mode .ticket-create-wrap .note-editor .note-placeholder {
			color: #adb5bd !important;
		}
	</style>
	<div class="ticket-create-wrap">
	<form class="ticket-create-form" onsubmit="page.submit(event)" method="post" enctype="multipart/form-data">
		<div class="ticket-create-card">
			<?=csrf()?>
			<div class="ticket-create-heading">
				<span class="ticket-create-heading-icon" aria-hidden="true"><i class="fa-solid fa-ticket"></i></span>
				<div>
					<h1>Create a ticket</h1>
					<p>Tell the right person what you need help with.</p>
				</div>
			</div>
			<div class="ticket-create-body">
				<div class="ticket-create-online mb-4">
					<?=tickets_online_users_strip_html($onlineUserRows ?? [])?>
				</div>
	 
			<div class="mb-4">
				<label for="subject" class="form-label">Subject</label>
				<select class="form-select" name="subject" id="subject" required>
				  <option value="">---Select---</option>
				  <?php $subjectOptions = $subjectOptions ?? []; ?>
				  <?php if(!empty($subjectOptions)):?>
				  	<?php foreach($subjectOptions as $opt): $opt = (string)$opt;?>
				  		<option <?=old_select('subject',$opt)?> value="<?=esc($opt)?>"><?=esc($opt)?></option>
				  	<?php endforeach;?>
				  <?php endif;?>
				</select>
				<?php if(!empty($errors['subject'])): ?>
				  <small class="text-danger"><?=esc((string)$errors['subject'])?></small>
				<?php endif ?>
			</div>

            <!-- Assign Dropdown -->
            <div class="mb-4">
                <label for="assigned" class="form-label">Send to</label>
                <select class="form-select assigned" name="assigned" id="assigned" required>
				  <option value="">---Select---</option>
				  <?php if(!empty($users)):?>
				  	<?php foreach ($users as $user): ?>				  		
						<?php
							$isOnline = tickets_user_is_online($user->id ?? 0, $onlineUserIds ?? []);
							$name = trim((string)($user->first_name ?? '') . ' ' . (string)($user->last_name ?? ''));
						?>
				  		<option <?=old_select('assigned',$user->id ?? '', '')?> value="<?=$user->id ?? '' ?>">
							<?=esc($name)?> <?=$isOnline ? '(Online)' : '(Offline)'?>
						</option>
				  	<?php endforeach;?>
				  <?php endif;?>
			  </select>
			  <?php if(empty($users)): ?>
			  	<small class="text-warning d-block mt-1">No users have created a cashdrawer today.</small>
			  <?php else: ?>
			  	<small class="text-muted d-block mt-1">Only users with today&apos;s cashdrawer are listed. Online users are marked in the dropdown.</small>
			  <?php endif; ?>
                <?php if(!empty($errors['assigned'])): ?>
                    <small class="text-danger"><?=esc((string)$errors['assigned'])?></small>
                <?php endif ?>
            </div>

			<div class="mb-4">
			  <label for="description" class="form-label">Description:</label>
			  <textarea rows="5" id="description" name="description" class="summernote form-control"><?=esc((string)old_value('description'))?></textarea>
				<?php if(!empty($errors['description'])): ?>
				  <small class="text-danger"><?=esc((string)$errors['description'])?></small>
				<?php endif ?>
			</div>

            <!-- Status Dropdown -->
            <div class="mb-4">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select" required>
                    <option value="Open" <?=old_select('status', 'Open')?> selected readonly>Open</option>
                </select>
                <?php if(!empty($errors['status'])): ?>
                    <small class="text-danger"><?=esc((string)$errors['status'])?></small>
                <?php endif ?>
            </div>
 
	 		<div class="progress my-3 d-none" aria-label="Saving ticket">
	 			<div class="progress-bar" style="width:0%">0%</div>
	 		</div>
	 
			<div class="ticket-create-actions">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<span class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Back to tickets</span>
				</a>
				<button class="btn btn-primary px-4" type="submit">
					<i class="fa-solid fa-paper-plane me-1"></i>Create ticket
				</button>
			</div>
			</div>
		</div>
	</form>
	</div>

	<script type="text/javascript">
		function ticketSwal(title, message, icon) {
			if(window.basicAdminSwalAlert){
				return window.basicAdminSwalAlert(title, message, icon || 'info');
			}
			if(window.Swal && window.Swal.fire){
				return window.Swal.fire({title: title, text: message, icon: icon || 'info'});
			}
			console[(icon === 'error') ? 'error' : 'log']((title ? title + ': ' : '') + (message || ''));
			return Promise.resolve();
		}

		var valid_image = true;
		var image_added = false;
		function display_image(e)
		{
			let allowed = ['image/jpeg','image/png','image/webp'];
			let file = e.currentTarget.files[0];

			if(!allowed.includes(file.type)){
				ticketSwal('Invalid File Type', "Only files of this type allowed: " + allowed.toString().replaceAll('image/',''), 'error');
				valid_image = false;
				image_added = false;
				return;
			}
			valid_image = true;
			image_added = true;
			e.currentTarget.parentNode.querySelector('img').src = URL.createObjectURL(file);
		}

		function submit_form(e)
		{
			if(!valid_image)
			{
				e.preventDefault()
				ticketSwal('Invalid Image', 'Please add a valid image', 'error');
				return;
			}
		}

		const page = {

			uploading: false,

			submit: function(e)
			{
				e.preventDefault();
				const form = e.currentTarget;
				const progress = form.querySelector('.progress');
				const progressBar = form.querySelector('.progress-bar');

				if(page.uploading){
					ticketSwal('Please Wait', 'Please wait while we upload', 'info');
					return;
				}

				let inputs = form.querySelectorAll("input,select,textarea");

				if(progress && progressBar){
					progressBar.style.width = '0%';
					progressBar.textContent = 'Saving...0%';
					progress.classList.remove('d-none');
				}

				let myform = new FormData();
				for (var i = 0; i < inputs.length; i++) {

					if(inputs[i].type == 'file'){
						if(image_added)
							myform.append(inputs[i].name,inputs[i].files[0]);
					}else if(inputs[i].type == 'checkbox' || inputs[i].type == 'radio'){
						if(inputs[i].checked)
							myform.append(inputs[i].name,inputs[i].value);
					}else{

						if(inputs[i].name == 'title' && inputs[i].value.trim() == ''){

							ticketSwal('Missing Title', 'A title is required!', 'error');
							return;
						}
						myform.append(inputs[i].name,inputs[i].value);
					}
				}

				page.uploading = true;
				let xhr = new XMLHttpRequest();

				xhr.addEventListener('readystatechange',function(e){

					if(xhr.readyState == 4)
					{
						page.uploading = false;
						if(progress){
							progress.classList.add('d-none');
						}
						page.handleResult(xhr.responseText, xhr.status);
					}
				});

				xhr.upload.addEventListener('progress',function(e){

					let percent = Math.round((e.loaded / e.total) * 100);
					if(progressBar){
						progressBar.style.width = percent + '%';
						progressBar.textContent = 'Saving...' + percent + '%';
					}
				});
 
				xhr.open('post','',true);
				xhr.setRequestHeader('Accept', 'application/json');
				xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
				xhr.send(myform);
			},

			handleResult: function(result, status)
			{
				let obj;
				const responseText = String(result || '').replace(/^\uFEFF/, '').trim();
				try{
					obj = JSON.parse(responseText);
				}catch(error){
					obj = null;
					const start = responseText.indexOf('{');
					const last = responseText.lastIndexOf('}');
					if(start !== -1 && last > start){
						try{
							obj = JSON.parse(responseText.slice(start, last + 1));
						}catch(parseError){}
					}
				}
				if(!obj || typeof obj !== 'object'){
					const plainText = responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
					const detail = plainText && !plainText.startsWith('{')
						? plainText.slice(0, 300)
						: '';
					const message = detail || (status && status !== 200
						? 'The server could not add the ticket. Please try again.'
						: 'The server returned an invalid response. Please try again.');
					ticketSwal('Unable to Add Ticket', message, 'error');
					return;
				}
				if(obj.success){
						const done = function(){
							window.location.href = '<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>';
						};
						if(window.basicAdminSwalAlert){
							window.basicAdminSwalAlert('Ticket Added', obj.message || 'Record added successfully!', 'success').then(done);
						}else if(window.Swal && window.Swal.fire){
							window.Swal.fire({icon: 'success', title: 'Ticket Added', text: obj.message || 'Record added successfully!'}).then(done);
						}else{
							done();
						}
					}else{
						const errors = [];
						for(const key in (obj.errors || {})) errors.push(obj.errors[key]);
						const message = errors.join("\n") || obj.message || 'Unable to add ticket.';
						if(window.basicAdminSwalAlert){
							window.basicAdminSwalAlert('Unable to Add Ticket', message, 'error');
						}else if(window.Swal && window.Swal.fire){
							window.Swal.fire({icon: 'error', title: 'Unable to Add Ticket', text: message});
						}else{
							ticketSwal('Unable to Add Ticket', message, 'error');
						}
					}
			},
 
		}

	</script>

	<!-- include libraries(jQuery, bootstrap) -->
	<script src="<?=plugin_http_path('assets/js/jquery-3.7.1.min.js')?>"></script>

	<!-- include summernote css/js -->
	<link href="<?=plugin_http_path('summernote-0.8.18-dist/summernote-lite.min.css')?>" rel="stylesheet">
	<script src="<?=plugin_http_path('summernote-0.8.18-dist/summernote-lite.min.js')?>"></script>
	
	<script type="text/javascript">
	    window.addEventListener('load', function () {
	        $(document).ready(function () {
	            $('.summernote').summernote({
	                placeholder: 'Your message content here ...',
	                tabsize: 2,
	                height: 150, // Adjust editor height
	                toolbar: [
	                    // Define specific toolbars to include
	                    ['style', ['style']], // Style dropdown
	                    ['font', ['bold', 'italic', 'underline']], // Font styling
	                    ['para', ['ul', 'ol', 'paragraph']], // List and paragraph options
	                ],
	            });
	        });
	    });
	</script>
	<?=tickets_notification_script((int)($user_id ?? 0), $admin_route, $plugin_route)?>

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>
