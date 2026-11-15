<style>
	.form-control, .form-select{
		border: none;
		border-bottom: 0.5px solid lightgreen;
		border-radius: 40px;
	}

	legend{
		color: lightgreen;
		font-family: tahoma;
		font-weight: bolder;
	}
</style>

<?php
$session = get_session();
$userId = $session->id;

?>

<?php if(!empty($row)):?>
	<?php if(user_can('edit_profile') || $userId === $row->id):?>

			<form onsubmit="return validate_passwords(event)" method="post">
				
				<div class="row g-3 col-md-12 col-md-10  col-lg-6 mx-auto shadow p-4 rounded">
				
				<?=csrf()?>

				<h4 class=""><span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span> Reset Password</h4>

				<div class="text-center mb-3">
					<img src="<?=get_image($row->image, $row->gender)?>" class="img-thumbnail rounded-circle" style="cursor:pointer;width:100%;max-width:100px;max-height: 100px;object-fit: cover;">
					<p class="mt-2 fw-bold"><?=esc($row->first_name ?? '')?> <?=esc($row->last_name ?? '')?></p>
					<p class="text-muted"><?=esc($row->email ?? '')?></p>
				</div>

				<div class="mb-3 col-md-12">
				  <label for="new_password" class="form-label">New Password</label>
				  <input name="new_password" value="<?=old_value('new_password','')?>" type="password" class="form-control" id="new_password" placeholder="Enter new password" required>
				
					<?php if(!empty($errors['new_password'])):?>
					  <small class="text-danger"><?=$errors['new_password']?></small>
					<?php endif?>
				</div>

				<div class="mb-3 col-md-12">
				  <label for="confirm_password" class="form-label">Confirm Password</label>
				  <input name="confirm_password" value="<?=old_value('confirm_password','')?>" type="password" class="form-control" id="confirm_password" placeholder="Confirm new password" required>
				
					<?php if(!empty($errors['confirm_password'])):?>
					  <small class="text-danger"><?=$errors['confirm_password']?></small>
					<?php endif?>
				</div>
				
				<div class="d-flex justify-content-between">
					<a href="<?=ROOT?>/<?=$admin_route?>/profile/edit/<?=$row->id?>">
						<button type="button" class="btn btn-primary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</button>
					</a>
					<button type="submit" class="btn btn-danger">
						<i class="fa-solid fa-save"></i> Reset Password
					</button>
				</div>
			</div>
		</form>
		<script type="text/javascript">
			function validate_passwords(e)
			{
				let newPassword = document.getElementById('new_password').value;
				let confirmPassword = document.getElementById('confirm_password').value;
				
				if(newPassword !== confirmPassword) {
					e.preventDefault();
					Swal.fire({
						icon: 'error',
						title: 'Oops...',
						text: 'Passwords do not match!'
					});
					return false;
				}
				
				if(newPassword.length < 6) {
					e.preventDefault();
					Swal.fire({
						icon: 'warning',
						title: 'Password Too Short',
						text: 'Password must be at least 6 characters long!'
					});
					return false;
				}
				
				return true;
			}
		</script>
	<?php else:?>
		<div class="alert alert-danger text-center">
			Access denied. You dont have permission for this action
		</div>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		That record was not found!
	</div>
	
	<a href="<?=ROOT?>/<?=$admin_route?>/profile/edit/<?=$row->id ?? ''?>">
		<button class="btn btn-primary">Back</button>
	</a>
<?php endif?>
