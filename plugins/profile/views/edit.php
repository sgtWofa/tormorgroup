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


			<form onsubmit="submit_form(event)" method="post" enctype="multipart/form-data">
				
				<div class="row g-3 col-md-12 col-md-10  col-lg-6 mx-auto shadow p-4 rounded">
				
				<?=csrf()?>

				<h4 class=""><span><img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:60px;max-height: 60px;object-fit: cover;"></span> Edit Record</h4>
				
				<label class="text-center">
					<img src="<?=get_image($row->image, $row->gender)?>" class="img-thumbnail" style="cursor:pointer;width:100%;max-width:200px;max-height: 200px;object-fit: cover;">
					<input onchange="display_image(event)" type="file" name="image" class="d-none">
					
					<?php if(!empty($errors['image'])):?>
					  <small class="text-danger"><?=$errors['image']?></small>
					<?php endif?>
				</label>

				<div class="mb-3 col-md-6">
				  <label for="first_name" class="form-label">First Name</label>
				  <input name="first_name" value="<?=old_value('first_name',$row->first_name)?>" type="text" class="form-control" id="first_name" placeholder="First Name">
					
					<?php if(!empty($errors['first_name'])):?>
					  <small class="text-danger"><?=$errors['first_name']?></small>
					<?php endif?>
				</div>

				<div class="mb-3 col-md-6">
				  <label for="last_name" class="form-label">Last Name</label>
				  <input name="last_name" value="<?=old_value('last_name',$row->last_name)?>" type="text" class="form-control" id="last_name" placeholder="Last Name">
					
					<?php if(!empty($errors['last_name'])):?>
					  <small class="text-danger"><?=$errors['last_name']?></small>
					<?php endif?>
				</div>
				
				<div class="mb-3 col-md-6">
				  <label for="email" class="form-label">Email</label>
				  <input name="email" value="<?=old_value('email',$row->email)?>" type="email" class="form-control" id="last_name" placeholder="Email">
				
					<?php if(!empty($errors['email'])):?>
					  <small class="text-danger"><?=$errors['email']?></small>
					<?php endif?>
				</div>

				<div class="mb-3 col-md-6">
					<label for="email" class="form-label">Gender</label>
					<select class="form-select" name="gender">
					  <option value="">--Select Gender--</option>
					  <option <?=old_select('gender','male',$row->gender)?> value="male">Male</option>
					  <option <?=old_select('gender','female',$row->gender)?> value="female">Female</option>
					</select>

					<?php if(!empty($errors['gender'])):?>
					  <small class="text-danger"><?=$errors['gender']?></small>
					<?php endif?>
				</div>

				<div class="mb-3 col-md-12 border p-2">
					<label>Roles:</label>
					<div class="row g-2">
						<?php 
							$query = "select * from user_roles where disabled = 0";
							$roles = $user_role->query($query); 

						?>
						<?php if(user_can("edit_role")):?>

								<?php if(!empty($roles)):$num = 0?>
									<?php foreach($roles as $role):$num++?>
										<div class="form-check col-md-6">
										  <input <?=in_array($role->id, $row->role_ids ?? []) ? ' checked ':''?> name="role_<?=$num?>" class="form-check-input" type="checkbox" value="<?=$role->id?>" id="check<?=$num?>">
										  <label class="form-check-label" for="check<?=$num?>" style="cursor:pointer;">
										    <?=esc(str_replace("_", " ", $role->role))?>
										  </label>
										</div>
									<?php endforeach?>
								<?php endif?>
							<?php else:?>								

							<?php if(!empty($roles)):$num = 0?>
								<?php foreach($roles as $role):$num++?>
									<div class="form-check col-md-6">
									  <input <?=in_array($role->id, $row->role_ids ?? []) ? ' checked ':'disabled'?> name="role_<?=$num?>" class="form-check-input" type="checkbox" value="<?=$role->id?>" id="check<?=$num?>">
									  <label class="form-check-label" for="check<?=$num?>" style="cursor:pointer;">
									    <?=esc(str_replace("_", " ", $role->role))?>
									  </label>
									</div>
								<?php endforeach?>
							<?php endif?>
						<?php endif;?>
					</div>

				</div>

				 <small class="text-danger">(Leave password empty to keep the old one)</small>
				<div class="mb-3 col-md-6">
				  <label for="password" class="form-label">Password</label>
				  <input name="password" value="<?=old_value('password','')?>" type="password" class="form-control" id="password" placeholder="Password">
				
				  	<?php if(!empty($errors['password'])):?>
					  <small class="text-danger"><?=$errors['password']?></small>
					<?php endif?>
				</div>

				<div class="mb-3 col-md-6">
				  <label for="retype_password" class="form-label">Retype Password</label>
				  <input name="retype_password" type="password" class="form-control" id="retype_password" placeholder="Retype password">
				</div>
				
				<div class="d-flex justify-content-between">
					<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
						<button type="button" class="btn btn-primary">
							<i class="fa-solid fa-chevron-left"></i> Back
						</button>
					</a>
					<button class="btn btn-danger">
						<i class="fa-solid fa-save"></i> Save
					</button>
				</div>
			</div>
		</form>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
<script type="text/javascript">

	var valid_image = true;
	function display_image(e)
	{
		const inputEl = e.currentTarget;
		if(window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function'){
			window.AppImageCropper.handleLegacyChange(e, {
				aspectRatio: 1,
				maxBytes: window.AppImageCropper.MAX_BYTES,
				modalTitle: 'Crop profile image',
				useAlert: true,
				errorTitle: 'Invalid image'
			}).then(function(result){
				valid_image = !result || !!result.ok || !!result.cancelled;
			});
			return;
		}

		let allowed = ['image/jpeg','image/png','image/webp'];
		let file = inputEl.files[0];

		if(!allowed.includes(file.type)){
			alert("Only files of this type allowed: " + allowed.toString().replaceAll('image/',''));
			valid_image = false;
			return;
		}
		valid_image = true;
		inputEl.parentNode.querySelector('img').src = URL.createObjectURL(file);
	}

	function submit_form(e)
	{
		if(!valid_image)
		{
			e.preventDefault()
			alert("Please add a valid image");
			return;
		}
	}

</script>

<?php elseif(!empty($studentRow)):?>
	<?php if(user_can('edit_profile') || $userId === $studentRow->id):?>
	<div class="row g-3 col-md-12 col-sm-12 col-lg-10 my-2 mx-auto shadow p-4 rounded">
		
			<form onsubmit="submit_form(event)" method="post" enctype="multipart/form-data" style="border:thin solid lightgreen; margin-top:70px;">			
		
		<?=csrf()?>

		<center>
			<?php if(message_success()):?>
				<div class="alert alert-success text-center">
					<?=esc(message_success('',true))?>
				</div>
			<?php endif?>
			<?php if(message_fail()):?>
				<div class="alert alert-danger text-center">
					<?=esc(message_fail('',true))?>
				</div>
			<?php endif?>

			<h4 class="fw-bolder"><i class="fas fa-user-shied"></i><br> Update Student</h4>
		</center>
		
		<label class="text-right">
			<img src="<?=get_image($studentRow->image, $studentRow->gender)?>" class="img-thumbnail" style="cursor:pointer;width:100%;max-width:200px;max-height: 200px;object-fit: cover; background:transparent;">
			<input onchange="display_image(event)" type="file" name="image" class="d-none">
			
			<?php if(!empty($errors['image'])):?>
			  <small class="text-danger"><?=$errors['image']?></small>
			<?php endif?>
		</label>

		<div id="phase_1" class=" row d-flex px-2">
			<legend>PERSONAL INFORMATION</legend>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input value="<?=$studentRow->index_number?>" name="index_number" type="text" class="form-control d-none" placeholder="Wofa"  >
			  <div id="floatingInput"  class="form-control" disabled ><?=$studentRow->index_number?></div>
			  <label for="floatingInput">Index number</label>

			  	<?php if(!empty($errors['index_number'])):?>
				  <small class="text-danger px-2"><?=$errors['index_number']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input id="floatingInput" value="<?=old_value('first_name',$studentRow->first_name)?>" name="first_name" type="text" class="form-control" id="floatingInput" placeholder="Wofa"  >
			  <label for="floatingInput">First Name</label>

			  	<?php if(!empty($errors['first_name'])):?>
				  <small class="text-danger px-2"><?=$errors['first_name']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input id="floatingInput" value="<?=old_value('last_name',$studentRow->last_name)?>" name="last_name" type="text" class="form-control" id="floatingInput" placeholder="Sam"  >
			  <label for="floatingInput">Last Name</label>

			  	<?php if(!empty($errors['last_name'])):?>
				  <small class="text-danger px-2"><?=$errors['last_name']?></small>
				<?php endif?>
			</div>

			<div class="form-floating mb-3  col-lg-4 col-md-6 col-sm-12">
				<select id="floatingInput" class="form-select form-control" name="gender">
				  <option value="">--Select Gender--</option>
				  <option <?=old_select('gender','male', $studentRow->gender)?> value="male">Male</option>
				  <option <?=old_select('gender','female', $studentRow->gender)?> value="female">Female</option>
				</select>
				<label for="floatingInput" class="form-label">Gender</label>

				<?php if(!empty($errors['gender'])):?>
				  <small class="text-danger"><?=$errors['gender']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input id="floatingInput" value="<?=old_value('dob',$studentRow->dob)?>" name="dob" type="date" class="form-control" id="floatingInput" placeholder=""  >
			  <label for="floatingInput">Date of Birth</label>

			  	<?php if(!empty($errors['dob'])):?>
				  <small class="text-danger px-2"><?=$errors['dob']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			    <input id="floatingInput" value="<?=old_value('age')?>" name="age" type="text" class="form-control" id="floatingInput" placeholder="Age"  >
			    <label for="floatingInput">Age</label>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input id="floatingInput" value="<?=old_value('residence',$studentRow->residence)?>" name="residence" type="text" class="form-control" id="floatingInput" placeholder=""  >
			  <label for="floatingInput">Residence <small>(Where do you stay?)</small></label>

			  	<?php if(!empty($errors['residence'])):?>
				  <small class="text-danger px-2"><?=$errors['residence']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input id="floatingInput" value="<?=old_value('hometown',$studentRow->hometown)?>" name="hometown" type="text" class="form-control" id="floatingInput" placeholder=""  >
			  <label for="floatingInput">Home Town <small>(Where do you come from?)</small></label>

			  	<?php if(!empty($errors['hometown'])):?>
				  <small class="text-danger px-2"><?=$errors['hometown']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
				<select id="floatingInput" class="form-select form-control p-3 my-2" name="region" >
					<option value="">--Region--</option>
					<option <?=old_select('region','Ahafo',$studentRow->region)?> value="Ahafo">Ahafo</option>
					<option <?=old_select('region','Ashanti',$studentRow->region)?> value="Ashanti">Ashanti</option>
					<option <?=old_select('region','Bono',$studentRow->region)?> value="Bono">Bono</option>
					<option <?=old_select('region','Bono East',$studentRow->region)?> value="Bono East">Bono East</option>
					<option <?=old_select('region','Central',$studentRow->region)?> value="Central">Central</option>
					<option <?=old_select('region','Eastern',$studentRow->region)?> value="Eastern">Eastern</option>
					<option <?=old_select('region','Greater Accra',$studentRow->region)?> value="Greater Accra">Greater Accra</option>
					<option <?=old_select('region','Northern',$studentRow->region)?> value="Northern">Northern</option>
					<option <?=old_select('region','North East',$studentRow->region)?> value="North East">North East</option>
					<option <?=old_select('region','Oti',$studentRow->region)?> value="Oti">Oti</option>
					<option <?=old_select('region','Savanna',$studentRow->region)?> value="Savanna">Savanna</option>
					<option <?=old_select('region','Upper East',$studentRow->region)?> value="Upper East">Upper East</option>
					<option <?=old_select('region','Upper West',$studentRow->region)?> value="Upper West">Upper West</option>
					<option <?=old_select('region','Volta',$studentRow->region)?> value="Volta">Volta</option>
					<option <?=old_select('region','Western',$studentRow->region)?> value="Western">Western</option>
					<option <?=old_select('region','Western North',$studentRow->region)?> value="Western North">Western North</option>
				</select>
				<label for="floatingInput">Region</label>
				<?php if(!empty($errors['region'])):?>
				  <small class="text-danger px-2"><?=$errors['region']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input id="floatingInput" value="<?=old_value('address',$studentRow->address)?>" name="address" type="text" class="form-control" id="floatingInput" placeholder=""  >
			  <label for="floatingInput">Address  <small>(Digital Address/Residential address?)</small></label>

			  	<?php if(!empty($errors['address'])):?>
				  <small class="text-danger px-2"><?=$errors['address']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input id="floatingInput" value="<?=old_value('postal_address',$studentRow->postal_address)?>" name="postal_address" type="text" class="form-control" id="floatingInput" placeholder=""  >
			  <label for="floatingInput">Postal Address </label>

			  	<?php if(!empty($errors['postal_address'])):?>
				  <small class="text-danger px-2"><?=$errors['postal_address']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input id="floatingInput" value="<?=old_value('languages_spoken', $studentRow->languages_spoken)?>" name="languages_spoken" type="text" class="form-control" id="floatingInput" placeholder=""  >
			  <label for="floatingInput">Languages Spoken</label>

			  	<?php if(!empty($errors['languages_spoken'])):?>
				  <small class="text-danger px-2"><?=$errors['languages_spoken']?></small>
				<?php endif?>
			</div>

			<div class="mb-3  col-lg-4 col-md-6 col-sm-12">
				<label>Role:</label>
				<div class="row g-2">
					<?php 
						$query = "select * from user_roles where disabled = 0 and role = 'student'";
						$roles = $user_role->query($query); 

					?>

					<?php if(!empty($roles)):$num = 0?>
						<?php foreach($roles as $role):$num++?>
							<div class="form-check col-md-6">
							  <input id="floatingInput" name="role_<?=$num?>" checked class="form-check-input" type="checkbox" value="<?=$role->id?>" id="check<?=$num?>">
							  <label class="form-check-label" for="floatingInput<?=$num?>" style="cursor:pointer;">
							    <?=esc(str_replace("_", " ", $role->role))?>
							  </label>
							</div>
						<?php endforeach?>
					<?php endif?>
				</div>

			</div>

			<small class="text-danger">(Leave password empty to keep the old one)</small>
			<div class="mb-3 col-lg-4 col-md-6 col-sm-12">
			  <label for="password" class="form-label">Password</label>
			  <input name="password" value="<?=old_value('password','')?>" type="password" class="form-control" id="password" placeholder="Password">
			
			  	<?php if(!empty($errors['password'])):?>
				  <small class="text-danger"><?=$errors['password']?></small>
				<?php endif?>
			</div>

			<div class="mb-3 col-lg-4 col-md-6 col-sm-12">
			  <label for="retype_password" class="form-label">Retype Password</label>
			  <input name="retype_password" type="password" class="form-control" id="retype_password" placeholder="Retype password">
			</div>
		</div>

		<div id="phase_3" class="bg-muted rounded row d-flex">
			<legend>PREVIOUS SCHOOL'S INFORMATION</legend>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input value="<?=old_value('prev_school_name',$studentRow->prev_school_name)?>" name="prev_school_name" type="text" class="form-control" id="floatingInput" placeholder="name@example.com"  >
			  <label for="floatingInput">Previous School Name</label>

			  	<?php if(!empty($errors['prev_school_name'])):?>
				  <small class="text-danger px-2"><?=$errors['prev_school_name']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input value="<?=old_value('school_phone',$studentRow->school_phone)?>" name="school_phone" type="school_phone" class="form-control" id="floatingInput" placeholder="0244123456"  >
			  <label for="floatingInput">Phone number</label>

			  	<?php if(!empty($errors['school_phone'])):?>
				  <small class="text-danger px-2"><?=$errors['school_phone']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input value="<?=old_value('year_completed', $studentRow->year_completed)?>" name="year_completed" type="number" class="form-control" id="floatingInput" placeholder="name@example.com"  >
			  <label for="floatingInput">Year of Completion</label>

			  	<?php if(!empty($errors['year_completed'])):?>
				  <small class="text-danger px-2"><?=$errors['year_completed']?></small>
				<?php endif?>
			</div>
			
			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input value="<?=old_value('school_address', $studentRow->school_address)?>" name="school_address" type="school_address" class="form-control" id="floatingPassword" placeholder="Previous School Address"  >
			  <label for="floatingInput">School Address</label>

			  	<?php if(!empty($errors['school_address'])):?>
				  <small class="text-danger px-2"><?=$errors['school_address']?></small>
				<?php endif?>
			</div>
		</div>

		<div id="phase_3" class="bg-muted rounded row d-flex">
			<legend>CAREGIVER'S INFORMATION</legend>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input value="<?=old_value('caregiver_first_name', $studentRow->caregiver_first_name)?>" name="caregiver_first_name" type="text" class="form-control" id="floatingInput" placeholder="name@example.com"  >
			  <label for="floatingInput">Cargiver's First Name</label>

			  	<?php if(!empty($errors['caregiver_first_name'])):?>
				  <small class="text-danger px-2"><?=$errors['caregiver_first_name']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input value="<?=old_value('caregiver_last_name', $studentRow->caregiver_last_name)?>" name="caregiver_last_name" type="text" class="form-control" id="floatingInput" placeholder="name@example.com"  >
			  <label for="floatingInput">Caregiver's Last Name</label>

			  	<?php if(!empty($errors['caregiver_last_name'])):?>
				  <small class="text-danger px-2"><?=$errors['caregiver_last_name']?></small>
				<?php endif?>
			</div>

			<div class="form-floating my-2 col-lg-4 col-md-6 col-sm-12">
			  <input value="<?=old_value('caregiver_phone', $studentRow->caregiver_phone)?>" name="caregiver_phone" type="caregiver_phone" class="form-control" id="floatingInput" placeholder="0244123456"  >
			  <label for="floatingInput">Phone number</label>

			  	<?php if(!empty($errors['caregiver_phone'])):?>
				  <small class="text-danger px-2"><?=$errors['caregiver_phone']?></small>
				<?php endif?>
			</div>
			
			<div class="form-floating col-lg-4 col-md-6 col-sm-12">
				<select class="form-select p-3 my-2" name="relation" >
					<option value="">--Select Relation--</option>
					<option <?=old_select('relation','father', $studentRow->relation)?> value="father">Father</option>
					<option <?=old_select('relation','mother', $studentRow->relation)?> value="mother">Mother</option>
					<option <?=old_select('relation','guidian', $studentRow->relation)?> value="guidian">Guidian</option>
				</select>
				<label for="floatingInput">Relationship:</label>
				<?php if(!empty($errors['relation'])):?>
				  <small class="text-danger px-2"><?=$errors['relation']?></small>
				<?php endif?>
			</div>
		</div>
		
		<div class="d-flex justify-content-between mb-3">
			<a href="<?=ROOT?>/admin/profile/view/<?=$studentRow->id?>">
				<button type="button" class="btn btn-primary">
					<i class="fa-solid fa-chevron-left"></i> Back
				</button>
			</a>
			<button class="btn btn-danger">
				<i class="fa-solid fa-save"></i> Save
			</button>
		</div>
	</form>
	</div>

	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>
		
		<a href="<?=ROOT?>/admin/profile/view/<?=$studentRow->id?>?>">
			<button class="btn btn-primary">Back</button>
		</a>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>

<script type="text/javascript">

		let valid_image = true;
		function display_image(e)
		{
			const inputEl = e.currentTarget;
			if(window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function'){
				window.AppImageCropper.handleLegacyChange(e, {
					aspectRatio: 1,
					maxBytes: window.AppImageCropper.MAX_BYTES,
					modalTitle: 'Crop profile image',
					useAlert: true,
					errorTitle: 'Invalid image'
				}).then(function(result){
					valid_image = !result || !!result.ok || !!result.cancelled;
				});
				return;
			}

			let allowed = ['image/jpeg','image/png','image/webp'];
			let file = inputEl.files[0];

			if(!allowed.includes(file.type)){
				alert("Only files of this type allowed: " + allowed.toString().replaceAll('image/',''));
				valid_image = false;
				return;
			}
			valid_image = true;
			inputEl.parentNode.querySelector('img').src = URL.createObjectURL(file);
		}

		function submit_form(e)
		{
			if(!valid_image)
			{
				e.preventDefault()
				alert("Please add a valid image");
				return;
			}
		}


    // Function to calculate age
    function calculateAge(birthDate) {
        var today = new Date();
        var dob = new Date(birthDate);
        var age = today.getFullYear() - dob.getFullYear();
        var m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) {
            age--;
        }
        return age;
    }

    // Function to update age field value when date of birth changes
    function updateAge() {
        var dobField = document.querySelector('[name="dob"]');
        var ageField = document.querySelector('[name="age"]');
        if (dobField && ageField) {
            var age = calculateAge(dobField.value);
            ageField.value = age;
        }
    }

    window.addEventListener('load', updateAge);

    // Attach event listener to date of birth field
    var dobField = document.querySelector('[name="dob"]');
    if (dobField) {
        dobField.addEventListener('change', updateAge);
    }
</script>


