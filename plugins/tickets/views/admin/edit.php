<?php
/**
 * @var object $row
 * @var string $admin_route
 * @var string $plugin_route
 * @var array $vars
 */
?>
<?php if(user_can('edit_tickets')): ?>
	<style>
		.ticket-edit-page { max-width:820px; margin:1.5rem auto; padding:clamp(1rem,3vw,2rem); border:1px solid var(--bs-border-color); border-radius:1rem; background:var(--bs-body-bg); box-shadow:0 .8rem 2rem rgba(20,35,60,.08); }
		.ticket-edit-heading { display:flex; align-items:center; gap:.9rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--bs-border-color); }
		.ticket-edit-heading img { width:3rem; height:3rem; object-fit:contain; }
		.ticket-edit-heading h1 { margin:0; font-size:1.4rem; font-weight:700; letter-spacing:-.03em; }
		.ticket-edit-page .form-label { margin-bottom:.4rem; color:var(--bs-body-color); font-size:.875rem; font-weight:650; }
		.ticket-edit-page .form-control,.ticket-edit-page .form-select { min-height:2.8rem; border:1px solid var(--bs-border-color)!important; border-radius:.65rem!important; }
		.ticket-edit-page .form-control:focus,.ticket-edit-page .form-select:focus { border-color:rgba(13,110,253,.65)!important; box-shadow:0 0 0 .2rem rgba(13,110,253,.13); }
		.ticket-edit-page .ticket-subject { padding:.8rem 1rem; border:1px solid var(--bs-border-color); border-radius:.65rem; background:var(--bs-tertiary-bg); font-weight:600; }
		.ticket-edit-page .ticket-form-actions { display:flex; justify-content:space-between; gap:.75rem; margin-top:.5rem; padding-top:1rem; border-top:1px solid var(--bs-border-color); }
		.ticket-edit-page .progress { height:.55rem; border-radius:99px; }
	</style>
	<form onsubmit="page.submit(event)" method="post" enctype="multipart/form-data">		
		<div class="row g-3 ticket-edit-page">
			
			<?=csrf()?>

			<div class="ticket-edit-heading">
				<img src="<?=APP_LOGO2 ?? ''?>" alt="">
				<div>
					<h1>Update ticket</h1>
					<div class="text-muted small">Update the description, assignee, or status.</div>
				</div>
			</div>

			<div class="mb-3 col-md-12 col-lg-12 description">
			  <label for="description" class="form-label">Description:</label>
			  <textarea rows="5" id="description" name="description" class="summernote form-control"><?=esc((string)old_value('description', $row->description ?? ''))?></textarea>
				<?php if(!empty($errors['description'])): ?>
				  <small class="text-danger"><?=$errors['description']?></small>
				<?php endif ?>
			</div>
	 
			<div class="mb-2 col-md-12">
				<label for="subject" class="form-label">Subject</label>
				<div class="ticket-subject"><?=esc($row->subject ?? '')?></div>
				<select class="form-select d-none" name="subject" id="subject">	
				  <option value="">---Select---</option>
				  <?php $subjectOptions = $subjectOptions ?? []; ?>
				  <?php if(!empty($subjectOptions)):?>
				  	<?php foreach($subjectOptions as $opt): $opt = (string)$opt;?>
				  		<option <?=old_select('subject',$opt,$row->subject ?? '')?> value="<?=esc($opt)?>"><?=esc($opt)?></option>
				  	<?php endforeach;?>
				  <?php endif;?>
				</select>
				<?php if(!empty($errors['subject'])): ?>
				  <small class="text-danger"><?=$errors['subject']?></small>
				<?php endif ?>
			</div>

            <!-- Assign Dropdown -->
            <div class="mb-3 col-md-12">
                <label for="assigned" class="form-label">Assigned</label>
                <select class="form-select assigned" name="assigned">
				  <option value="">---Select---</option>
				  <?php if(!empty($users)):?>
				  	<?php foreach ($users as $user): ?>				  		
				  		<option <?=old_select('assigned',$user->id, $row->assigned ?? '')?> value="<?=$user->id ?? ' ' ?>"><?=$user->first_name . ' '. $user->last_name ?? ''?> </option>
				  	<?php endforeach;?>
				  	<?php endif;?>
			  </select>
                <?php if(!empty($errors['assigned'])): ?>
                    <small class="text-danger"><?=$errors['assigned']?></small>
                <?php endif ?>
            </div>

            <!-- Status Dropdown -->
            <div class="mb-3 col-md-12">
                <label for="status" class="form-label">Status</label>
                <select name="status" id="status" class="form-select" required>
                    <option value="Open" <?=old_select('status', 'Open,', $row->status ?? '')?>>Open</option>
                    <option value="In Progress" <?=old_select('status', 'In Progress,', $row->status ?? '')?>>In Progress</option>
                    <option value="Closed" <?=old_select('status', 'Closed,', $row->status ?? '')?>>Closed</option>
                </select>
                <?php if(!empty($errors['status'])): ?>
                    <small class="text-danger"><?=$errors['status']?></small>
                <?php endif ?>
            </div>

 
	 		<div class="progress my-1 d-none">
	 			<div class="progress-bar" style="width:0%">0%</div>
	 		</div>
	 
			<div class="ticket-form-actions">
				<a class="btn btn-outline-secondary" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<i class="fa-solid fa-arrow-left me-1"></i>Back to tickets
				</a>
				<button class="btn btn-primary px-4" type="submit">
					<i class="fa-solid fa-check me-1"></i>Save changes
				</button>
			</div>
		</div>
	</form>

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

				if(page.uploading){
					ticketSwal('Please Wait', 'Please wait while we upload', 'info');
					return;
				}

				let inputs = e.currentTarget.querySelectorAll("input,select,textarea");

				const progress = e.currentTarget.querySelector('.progress');
				const progressBar = progress ? progress.querySelector('.progress-bar') : null;
				if (progress) progress.classList.remove('d-none');
				if (progressBar) {
					progressBar.style.width = '0%';
					progressBar.textContent = 'Saving... 0%';
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

						if(inputs[i].name == 'description' && inputs[i].value.trim() == ''){

							ticketSwal('Missing Description', 'A description is required!', 'error');
							return;
						}else
						if(inputs[i].name == 'subject' && inputs[i].value.trim() == ''){

							ticketSwal('Missing Subject', 'A subject is required!', 'error');
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
						if(xhr.status == 200)
						{
							page.handleResult(xhr.responseText);
						}
					}
				});

				xhr.upload.addEventListener('progress',function(e){

					let percent = Math.round((e.loaded / e.total) * 100);
					let prog = document.querySelector('.ticket-edit-page .progress-bar');
					if (prog) {
						prog.style.width = percent + '%';
						prog.textContent = 'Saving...' + percent + '%';
					}
				});
 
				xhr.open('post','',true);
				xhr.send(myform);
			},

			handleResult: function(result)
			{
				console.log(result);
				let obj = JSON.parse(result);
				if(typeof obj == 'object')
				{
					if(obj.success)
					{
						ticketSwal('Ticket Updated', obj.message || 'Record updated successfully!', 'success').then(function(){
							window.location.href = '<?=ROOT?>/<?=$vars['admin_route']?>/<?=$vars['plugin_route']?>';
						});
					}else{
						const messages = [];
						for(key in obj.errors)
						{
							messages.push(obj.errors[key]);
						}
						ticketSwal('Unable to Update Ticket', messages.join("\n") || obj.message || 'Unable to update ticket.', 'error');
					}
				}else{
					console.log(result);
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
		                ['style', ['style']], // Style dropdown
		                ['font', ['bold', 'italic', 'underline']], // Font styling
		                ['para', ['ul', 'ol', 'paragraph']], // List and paragraph options
		            ],
		            // Ensure content is correctly populated in the editor
		           callbacks: {
					    onInit: function() {
					        var descriptionContent = <?=json_encode(old_value('description', $row->description))?>;
					        $('.summernote').summernote('code', descriptionContent);
					    }
					}

		        });
		    });
		});

	</script>

<?php else: ?>
	<div class="alert alert-danger text-center">
		Access denied. You don’t have permission for this action.
	</div>
<?php endif ?>
