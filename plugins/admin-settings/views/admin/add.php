<?php if(user_can('add_settings')):?>
		<style type="text/css">
			select option{
				color: darkblue !important;
			}
			.settings-shell{
				max-width: 1100px;
			}
			.settings-card{
				border: 1px solid rgba(0,0,0,.06);
				border-radius: 16px;
				box-shadow: 0 12px 30px rgba(16,24,40,.08);
				background: var(--bs-body-bg);
			}

			.settings-card .card-header{
				background: linear-gradient(135deg, rgba(65,84,241,.10), rgba(65,84,241,.03));
				border-bottom: 1px solid rgba(0,0,0,.06);
			}
			.settings-section-title{
				font-weight: 700;
				letter-spacing: .02em;
			}
			.settings-help{
				color: var(--bs-secondary-color);
				font-size: .9rem;
			}
			.settings-label{
				min-width: 220px;
			}
			@media (max-width: 768px){
				.settings-label{min-width: 140px;}
			}
			.settings-tabs{
				overflow-x: auto;
				-webkit-overflow-scrolling: touch;
				padding: .55rem;
				border: 1px solid rgba(0,0,0,.08);
				border-radius: 16px;
				background: rgba(13,110,253,.045);
			}
			.settings-tabs .nav{
				flex-wrap: wrap;
			}
			.settings-tabs .nav-link{
				white-space: nowrap;
				border-radius: 999px;
				font-weight: 900;
				border: 1px solid rgba(13,110,253,0.12);
				background: rgba(13,110,253,0.06);
				padding: .55rem .95rem;
				display: inline-flex;
				align-items: center;
				gap: .45rem;
			}
			.settings-tabs .nav-link.active,
			.settings-tabs .nav-link:focus,
			.settings-tabs .nav-link:active{
				background-color: #ffc107 !important;
				border-color: #ffc107 !important;
				color: #000 !important;
			}
			.settings-card .tab-content,
			.settings-card .tab-pane,
			.settings-card .tab-pane.active,
			.settings-card .tab-pane.show{
				background: transparent !important;
			}
			.settings-card .tab-content{
				border: 1px solid rgba(0,0,0,.08);
				border-radius: 16px;
				padding: 1rem;
				background: rgba(255,255,255,.36) !important;
			}
			html[data-bs-theme="dark"] .settings-card .tab-content{
				border-color: rgba(255,255,255,.14);
				background: rgba(15,23,42,.22) !important;
			}
			.settings-card .input-group-text{
				background: var(--bs-body-bg);
				border-color: rgba(0,0,0,.06);
				color: var(--bs-secondary-color);
				font-weight: 900;
			}
			/* 2-column label/value layout using existing input-group markup */
			.settings-card .input-group{
				display: grid;
				grid-template-columns: 260px 1fr;
				align-items: start;
				gap: 10px;
			}
			.settings-card .input-group > .input-group-text{
				width: auto;
				border-radius: 12px;
				padding: .7rem .9rem;
				justify-content: flex-start;
				white-space: normal;
				line-height: 1.2;
			}
			.settings-card .input-group > .form-control,
			.settings-card .input-group > .form-select{
				width: 100%;
				border-radius: 12px;
			}
			.settings-card .input-group > .form-control,
			.settings-card .input-group > .form-select,
			.settings-card .input-group > .input-group-text{
				grid-row: 1;
			}
			.settings-card .input-group > .input-group-text{grid-column: 1;}
			.settings-card .input-group > .form-control,
			.settings-card .input-group > .form-select{grid-column: 2;}
			.settings-card .input-group > small.text-danger{
				grid-column: 2;
				grid-row: 2;
				margin-top: 6px;
			}
			.settings-card .input-group > .text-danger{
				grid-column: 2;
				grid-row: 2;
			}
			.settings-card .form-control,
			.settings-card .form-select{
				border-color: rgba(0,0,0,.06);
				border-radius: 12px;
			}
			.settings-card .input-group > .form-control,
			.settings-card .input-group > .form-select{
				border-top-left-radius: 12px;
				border-bottom-left-radius: 12px;
			}
			.settings-card .input-group > .input-group-text{
				border-top-right-radius: 12px;
				border-bottom-right-radius: 12px;
			}
			.settings-card .form-control:focus,
			.settings-card .form-select:focus{
				box-shadow: 0 0 0 .22rem rgba(13,110,253,.18);
			}
			html[data-bs-theme="dark"] .settings-card .form-control,
			html[data-bs-theme="dark"] .settings-card .form-select{
				background-color: rgba(2,6,23,.22);
				color: rgba(255,255,255,.92);
			}
			html[data-bs-theme="dark"] .settings-card .form-control::placeholder{
				color: rgba(255,255,255,.55);
			}
			.settings-card small.text-danger{
				display: block;
				margin-top: 6px;
			}
			.settings-switch{
				display: flex;
				align-items: center;
				gap: .75rem;
				min-height: 48px;
			}
			.settings-switch .form-check-input{
				width: 3.25rem;
				height: 1.65rem;
				cursor: pointer;
			}
			.volume-control{
				display: grid;
				grid-template-columns: 1fr auto;
				align-items: center;
				gap: .75rem;
				min-height: 48px;
			}
			.volume-slider{
				width: 100%;
				accent-color: #0d6efd;
				cursor: pointer;
			}
			.volume-value{
				min-width: 54px;
				text-align: center;
			}
			.sound-upload-control{
				display: grid;
				gap: .35rem;
			}
			@media (max-width: 768px){
				.settings-tabs .nav{flex-wrap: nowrap;}
			}

				/* Palette swatch styles */
				.palette-swatch{
					border-radius:6px;
					transition:transform .18s ease, box-shadow .18s ease, opacity .12s ease;
					cursor:pointer;
					display:inline-block;
					vertical-align:middle;
					outline: none;
				}
				.palette-swatch:hover{
					transform: translateY(-4px) scale(1.08);
					box-shadow: 0 8px 22px rgba(2,6,23,.28);
				}
				.palette-swatch.selected{
					box-shadow: 0 10px 30px rgba(13,110,253,.22);
					transform: scale(1.06);
					border: 2px solid rgba(13,110,253,.9);
				}
				.pill-color-preview{
					transition: box-shadow .22s ease, transform .18s ease, background-color .12s ease;
					border-radius:6px;
					min-width:36px;
					min-height:24px;
					display:inline-block;
				}
				.pill-color-preview.selected{
					transform: translateY(-2px) scale(1.02);
				}
			</style>

		<form onsubmit="page.submit(event)" action="<?=$form_action ?? ''?>" method="post" enctype="multipart/form-data">
			
			<div class="settings-shell mx-auto">
				<div class="card settings-card">
					<div class="card-header px-4 py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
						<div class="d-flex align-items-center gap-2">
							<img src="<?=APP_LOGO2 ?? ''?>" style="width:44px;height:44px;object-fit:cover;" class="rounded" />
							<div>
								<div class="settings-section-title">System Settings</div>
								<div class="settings-help">Set up your organization profile, rates and charges.</div>
							</div>
						</div>
						<span class="badge text-bg-warning">Not configured</span>
					</div>
					<div class="card-body p-4">
			
			<?=csrf()?>

			<div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
				<div>
					<strong>Branch management</strong>
					<div class="small text-muted">Create and maintain branches for user assignment.</div>
				</div>
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/branches" class="btn btn-outline-primary btn-sm">
					<i class="fa-solid fa-code-branch"></i> Manage branches
				</a>
			</div>

			<div class="settings-tabs mb-3">
				<div class="nav nav-pills gap-2" id="settings-tabs" role="tablist">
					<button class="nav-link active" id="tab-company-btn" data-bs-toggle="tab" data-bs-target="#tab-company" type="button" role="tab"><i class="fa-solid fa-building"></i> Company</button>
					<button class="nav-link" id="tab-charges-btn" data-bs-toggle="tab" data-bs-target="#tab-charges" type="button" role="tab"><i class="fa-solid fa-receipt"></i> Charges</button>
					<button class="nav-link" id="tab-ops-btn" data-bs-toggle="tab" data-bs-target="#tab-ops" type="button" role="tab"><i class="fa-solid fa-briefcase"></i> Operations</button>
					<button class="nav-link" id="tab-rates-btn" data-bs-toggle="tab" data-bs-target="#tab-rates" type="button" role="tab"><i class="fa-solid fa-chart-line"></i> Rates</button>
					<button class="nav-link" id="tab-sim-btn" data-bs-toggle="tab" data-bs-target="#tab-sim" type="button" role="tab"><i class="fa-solid fa-sim-card"></i> SIM & Hours</button>
					<button class="nav-link" id="tab-notifications-btn" data-bs-toggle="tab" data-bs-target="#tab-notifications" type="button" role="tab"><i class="fa-solid fa-bell"></i> Notifications</button>
				</div>
			</div>

			<div class="tab-content">
				<div class="tab-pane fade show active" id="tab-company" role="tabpanel" aria-labelledby="tab-company-btn" tabindex="0">
					<div class="row g-1">
						<label class="col-md-3 text-center">
							<img src="<?=get_image('')?>" class="img-thumbnail" style="cursor:pointer;width:100%;max-width:200px;max-height:200px;object-fit:contain;">
							<input onchange="display_image(event)" type="file" name="image" class="d-none">
							<label for="image" class="form-label fw-bolder text-muted">CEO's signature</label>

							<?php if(!empty($errors['image'])):?>
							  <small class="text-danger"><?=$errors['image']?></small>
							<?php endif?>
						</label>

						<div class="col-md-9">
							<div class="mb-3 col-md-12 input-group">
							  <label for="company_name" class="input-group-text fw-bolder text-muted settings-label">Company Name</label>
							  <input name="company_name" value="<?=old_value('company_name', APP_NAME)?>" type="text" class="form-control" id="company_name" placeholder="Company Name">
								
								<?php if(!empty($errors['company_name'])):?>
								  <small class="text-danger"><?=$errors['company_name']?></small>
								<?php endif?>
							</div>
							<div class="mb-3 col-md-12 input-group">
							  <label for="ceo_name" class="input-group-text fw-bolder text-muted settings-label">CEO's Name</label>
							  <input name="ceo_name" value="<?=old_value('ceo_name', 'CEO')?>" type="text" class="form-control" id="ceo_name" placeholder="CEO's Name">
								
								<?php if(!empty($errors['ceo_name'])):?>
								  <small class="text-danger"><?=$errors['ceo_name']?></small>
								<?php endif?>
							</div>

							<div class="mb-3 col-md-12 input-group">
							  <label for="title" class="input-group-text fw-bolder text-muted settings-label">Title</label>
							  	<select style="background: none;" class="form-select" name="title">
								  <option value="">--Select Title--</option>
								  <option <?=old_select('title','CEO')?> value="CEO">CEO</option>
								  <option <?=old_select('title','COO')?> value="COO">COO</option>
								  <option <?=old_select('title','Board Member')?> value="Board Member">Board Member</option>
								</select>
								
								<?php if(!empty($errors['title'])):?>
								  <small class="text-danger"><?=$errors['title']?></small>
								<?php endif?>
							</div>

							<div class="mb-3 col-md-12 input-group">
							  <label for="ceo_contact" class="input-group-text fw-bolder text-muted settings-label">CEO's Contact</label>
							  <input name="ceo_contact" value="<?=old_value('ceo_contact', '')?>" type="text" class="form-control" id="ceo_contact" placeholder="CEO's Contact">					
								<?php if(!empty($errors['ceo_contact'])):?>
								  <small class="text-danger"><?=$errors['ceo_contact']?></small>
								<?php endif?>
							</div>

							<div class="mb-3 col-md-12 input-group">
							  <label for="admin_contact" class="input-group-text fw-bolder text-muted settings-label">Office Contact</label>
							  <input name="admin_contact" value="<?=old_value('admin_contact', '')?>" type="text" class="form-control" id="admin_contact" placeholder="Office Contact">					
								<?php if(!empty($errors['admin_contact'])):?>
								  <small class="text-danger"><?=$errors['admin_contact']?></small>
								<?php endif?>
							</div>
						</div>
					</div>
				</div>

				<div class="tab-pane fade" id="tab-charges" role="tabpanel" aria-labelledby="tab-charges-btn" tabindex="0">
					<div class="mb-3 col-md-12 input-group">
					  <label for="transaction_fee" class="input-group-text fw-bolder text-muted settings-label">Transaction charges</label>
					  <input name="transaction_fee" value="<?=old_value('transaction_fee', 0.00)?>" type="text" class="form-control" id="transaction_fee" placeholder="0.00">					
						<?php if(!empty($errors['transaction_fee'])):?>
						  <small class="text-danger"><?=$errors['transaction_fee']?></small>
						<?php endif?>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="momo_charges" class="input-group-text fw-bolder text-muted settings-label">Momo charges</label>
					  <input name="momo_charges" value="<?=old_value('momo_charges', 0.00)?>" type="text" class="form-control" id="momo_charges" placeholder="0.00">					
						<?php if(!empty($errors['momo_charges'])):?>
						  <small class="text-danger"><?=$errors['momo_charges']?></small>
						<?php endif?>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="ezwich_charges" class="input-group-text fw-bolder text-muted settings-label">Ezwich charges</label>
					  <input name="ezwich_charges" value="<?=old_value('ezwich_charges', 0.00)?>" type="text" class="form-control" id="ezwich_charges" placeholder="0.00">					
						<?php if(!empty($errors['ezwich_charges'])):?>
						  <small class="text-danger"><?=$errors['ezwich_charges']?></small>
						<?php endif?>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="agencybanking_charges" class="input-group-text fw-bolder text-muted settings-label">Agency Banking charges</label>
					  <input name="agencybanking_charges" value="<?=old_value('agencybanking_charges', 0.00)?>" type="text" class="form-control" id="agencybanking_charges" placeholder="0.00">					
						<?php if(!empty($errors['agencybanking_charges'])):?>
						  <small class="text-danger"><?=$errors['agencybanking_charges']?></small>
						<?php endif?>
					</div>
				</div>

				<div class="tab-pane fade" id="tab-ops" role="tabpanel" aria-labelledby="tab-ops-btn" tabindex="0">
					<div class="mb-3 col-md-12 input-group">
					  <label for="date_started" class="input-group-text fw-bolder text-muted settings-label">Date Started</label>
					  <input name="date_started" value="<?=old_date('date_started', date('Y-m-d'))?>" type="date" class="form-control" id="date_started" placeholder="Date started">					
						<?php if(!empty($errors['date_started'])):?>
						  <small class="text-danger"><?=$errors['date_started']?></small>
						<?php endif?>
					</div>
				</div>

				<div class="tab-pane fade" id="tab-rates" role="tabpanel" aria-labelledby="tab-rates-btn" tabindex="0">
					<div class="mb-3 col-md-12 input-group">
					  <label for="buying_rate" class="input-group-text fw-bolder text-muted settings-label">CFA Buying rate : (CFA1000 to GHS)</label>
					  <input name="buying_rate" value="<?=old_value('buying_rate', 0.00)?>" type="text" class="form-control" id="buying_rate" placeholder="0.00">					
						<?php if(!empty($errors['buying_rate'])):?>
						  <small class="text-danger"><?=$errors['buying_rate']?></small>
						<?php endif?>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="selling_rate" class="input-group-text fw-bolder text-muted settings-label">CFA Selling rate : (CFA1000 to GHS)</label>
					  <input name="selling_rate" value="<?=old_value('selling_rate', 0.00)?>" type="text" class="form-control" id="selling_rate" placeholder="0.00">					
						<?php if(!empty($errors['selling_rate'])):?>
						  <small class="text-danger"><?=$errors['selling_rate']?></small>
						<?php endif?>
					</div>
				</div>

				<div class="tab-pane fade" id="tab-sim" role="tabpanel" aria-labelledby="tab-sim-btn" tabindex="0">
					<?php
						$formatTimeInputValue = static function($value, string $fallback = ''): string {
							$value = trim((string)$value);
							if($value === ''){
								return $fallback;
							}

							if(strlen($value) === 5){
								return $value;
							}

							$timeTs = strtotime('1970-01-01 ' . $value);
							return $timeTs !== false ? date('H:i', $timeTs) : $fallback;
						};

						$workOpenValue = $formatTimeInputValue(old_value('work_open', '08:00'), '08:00');
						$workCloseValue = $formatTimeInputValue(old_value('work_close', '17:00'), '17:00');
						$lateAttendanceValue = $formatTimeInputValue(old_value('late_attendance_time', ''));
						if($lateAttendanceValue === ''){
							$workOpenSeed = strlen($workOpenValue) === 5 ? $workOpenValue . ':00' : $workOpenValue;
							$workOpenTs = strtotime('1970-01-01 ' . $workOpenSeed);
							$lateAttendanceValue = $workOpenTs !== false ? date('H:i', $workOpenTs + (30 * 60)) : '08:30';
						}
					?>
					<div class="mb-3 col-md-12 input-group">
					  <label for="company_sim" class="input-group-text fw-bolder text-muted settings-label">Company Sim</label>
					  <input name="company_sim" value="<?=old_value('company_sim', '')?>" type="text" class="form-control" id="company_sim" placeholder="Company SIM">					
						<?php if(!empty($errors['company_sim'])):?>
						  <small class="text-danger"><?=$errors['company_sim']?></small>
						<?php endif?>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="inactivity_limit_minutes" class="input-group-text fw-bolder text-muted settings-label">Inactivity timeout (minutes)</label>
					  <input name="inactivity_limit_minutes" value="<?=old_value('inactivity_limit_minutes', 5)?>" type="number" min="1" step="1" class="form-control" id="inactivity_limit_minutes" placeholder="5">					
						<?php if(!empty($errors['inactivity_limit_minutes'])):?>
						  <small class="text-danger"><?=$errors['inactivity_limit_minutes']?></small>
						<?php endif?>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="work_open" class="input-group-text fw-bolder text-muted settings-label">Opening time</label>
					  <input name="work_open" value="<?=$workOpenValue?>" type="time" class="form-control" id="work_open" placeholder="Opening time">					
						<?php if(!empty($errors['work_open'])):?>
						  <small class="text-danger"><?=$errors['work_open']?></small>
						<?php endif?>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="work_close" class="input-group-text fw-bolder text-muted settings-label">Closing time</label>
					  <input name="work_close" value="<?=$workCloseValue?>" type="time" class="form-control" id="work_close" placeholder="Closing time">					
						<?php if(!empty($errors['work_close'])):?>
						  <small class="text-danger"><?=$errors['work_close']?></small>
						<?php endif?>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="late_attendance_time" class="input-group-text fw-bolder text-muted settings-label">Late attendance time</label>
					  <input name="late_attendance_time" value="<?=$lateAttendanceValue?>" type="time" class="form-control" id="late_attendance_time" placeholder="Late attendance time">
						<?php if(!empty($errors['late_attendance_time'])):?>
						  <small class="text-danger"><?=$errors['late_attendance_time']?></small>
						<?php endif?>
					</div>
				</div>

				<div class="tab-pane fade" id="tab-notifications" role="tabpanel" aria-labelledby="tab-notifications-btn" tabindex="0">
					<?php
						$selectedNotificationSound = old_value('notification_sound', '');
						$notificationSoundEnabled = (string)old_value('notification_sound_enabled', 1);
						$notificationSoundVolume = old_value('notification_sound_volume', 90);
					?>
					<div class="mb-3 col-md-12 input-group">
					  <label for="notification_sound_enabled" class="input-group-text fw-bolder text-muted settings-label">Notification sounds</label>
					  <div class="settings-switch form-control">
						  <input type="hidden" name="notification_sound_enabled" value="0">
						  <input class="form-check-input" name="notification_sound_enabled" type="checkbox" role="switch" id="notification_sound_enabled" value="1" <?=$notificationSoundEnabled === '1' ? 'checked' : ''?>>
						  <label class="form-check-label fw-semibold" for="notification_sound_enabled" id="notification_sound_enabled_label"><?=$notificationSoundEnabled === '1' ? 'Enabled' : 'Muted'?></label>
					  </div>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="notification_sound" class="input-group-text fw-bolder text-muted settings-label">Sound file</label>
					  <select name="notification_sound" class="form-select" id="notification_sound">
						  <option data-sound-url="" value="">Rotate all sounds in assets/sounds</option>
						  <?php foreach(($notificationSoundOptions ?? []) as $soundPath => $soundOption):?>
							  <option
								  <?=old_select('notification_sound', $soundPath, $selectedNotificationSound)?>
								  value="<?=htmlspecialchars((string)$soundPath, ENT_QUOTES, 'UTF-8')?>"
								  data-sound-url="<?=htmlspecialchars((string)($soundOption['url'] ?? ''), ENT_QUOTES, 'UTF-8')?>"
							  ><?=htmlspecialchars((string)($soundOption['label'] ?? $soundPath), ENT_QUOTES, 'UTF-8')?></option>
						  <?php endforeach?>
					  </select>
						<?php if(!empty($errors['notification_sound'])):?>
						  <small class="text-danger"><?=$errors['notification_sound']?></small>
						<?php endif?>
					</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="notification_sound_volume" class="input-group-text fw-bolder text-muted settings-label">Sound volume</label>
					  <div class="volume-control form-control">
						  <input name="notification_sound_volume" value="<?=$notificationSoundVolume?>" type="range" min="0" max="100" step="1" class="volume-slider" id="notification_sound_volume" aria-describedby="notification_sound_volume_value">
						  <span class="badge text-bg-primary volume-value" id="notification_sound_volume_value"><?=$notificationSoundVolume?>%</span>
					  </div>
						<?php if(!empty($errors['notification_sound_volume'])):?>
						  <small class="text-danger"><?=$errors['notification_sound_volume']?></small>
						<?php endif?>
					</div>

										<?php
											$seriousVal = old_value('notification_pill_color_serious', '#dc3545');
											$mildVal = old_value('notification_pill_color_mild', '#ffc107');
											$regularVal = old_value('notification_pill_color_regular', '#0d6efd');
										?>
										<div class="mb-3 col-md-12 input-group notification-palette-control" data-field="notification_pill_color_serious">
											<label for="notification_pill_color_serious" class="input-group-text fw-bolder text-muted settings-label">Serious pill color</label>
											<div class="d-flex gap-2 align-items-center w-100">
												<input name="notification_pill_color_serious" value="<?=htmlspecialchars((string)$seriousVal, ENT_QUOTES, 'UTF-8')?>" type="text" class="form-control pill-color-text" id="notification_pill_color_serious" placeholder="#dc3545 or bg-danger">
												<input type="color" class="form-control form-control-color pill-color-picker" value="<?=htmlspecialchars((string)$seriousVal, ENT_QUOTES, 'UTF-8')?>" title="Choose color" style="width:48px;padding:0;border:none;height:38px" />
												<span class="pill-color-preview rounded" style="display:inline-block;width:36px;height:24px;border:1px solid rgba(0,0,0,.08)"></span>
												<div class="ms-2 d-flex gap-1 palette">
													<?php foreach(['#dc3545','#c82333','#e11d48','#ff6b6b','#ff8787','#ffb3b3','#fd7e14','#ff922b','#6610f2','#6f42c1','#adb5bd','#6c757d','#000000','#ffffff'] as $c):?>
														<button type="button" class="btn btn-sm palette-swatch" data-color="<?=htmlspecialchars($c, ENT_QUOTES, 'UTF-8')?>" title="<?=htmlspecialchars($c)?>" style="background:<?=htmlspecialchars($c)?>;border:1px solid rgba(0,0,0,.08);width:22px;height:22px;padding:0"></button>
													<?php endforeach?>
													<button type="button" class="btn btn-sm btn-outline-secondary palette-info" data-category="serious" title="Show app pages for serious notifications" style="padding:.15rem .35rem"><i class="fa-solid fa-list"></i></button>
												</div>
											</div>
											<?php if(!empty($errors['notification_pill_color_serious'])):?>
												<small class="text-danger"><?=$errors['notification_pill_color_serious']?></small>
											<?php endif?>
										</div>

										<div class="mb-3 col-md-12 input-group notification-palette-control" data-field="notification_pill_color_mild">
											<label for="notification_pill_color_mild" class="input-group-text fw-bolder text-muted settings-label">Mild pill color</label>
											<div class="d-flex gap-2 align-items-center w-100">
												<input name="notification_pill_color_mild" value="<?=htmlspecialchars((string)$mildVal, ENT_QUOTES, 'UTF-8')?>" type="text" class="form-control pill-color-text" id="notification_pill_color_mild" placeholder="#ffc107 or bg-warning">
												<input type="color" class="form-control form-control-color pill-color-picker" value="<?=htmlspecialchars((string)$mildVal, ENT_QUOTES, 'UTF-8')?>" title="Choose color" style="width:48px;padding:0;border:none;height:38px" />
												<span class="pill-color-preview rounded" style="display:inline-block;width:36px;height:24px;border:1px solid rgba(0,0,0,.08)"></span>
												<div class="ms-2 d-flex gap-1 palette">
													<?php foreach(['#ffc107','#ffca2c','#ffdf7e','#fd7e14','#ff922b','#ffb020','#20c997','#0dcaf0','#17a2b8','#0d6efd','#3b82f6','#6610f2','#8e44ad','#adb5bd','#6c757d','#000000','#ffffff'] as $c):?>
														<button type="button" class="btn btn-sm palette-swatch" data-color="<?=htmlspecialchars($c, ENT_QUOTES, 'UTF-8')?>" title="<?=htmlspecialchars($c)?>" style="background:<?=htmlspecialchars($c)?>;border:1px solid rgba(0,0,0,.08);width:22px;height:22px;padding:0"></button>
													<?php endforeach?>
													<button type="button" class="btn btn-sm btn-outline-secondary palette-info" data-category="mild" title="Show app pages for mild notifications" style="padding:.15rem .35rem"><i class="fa-solid fa-list"></i></button>
												</div>
											</div>
											<?php if(!empty($errors['notification_pill_color_mild'])):?>
												<small class="text-danger"><?=$errors['notification_pill_color_mild']?></small>
											<?php endif?>
										</div>

										<div class="mb-3 col-md-12 input-group notification-palette-control" data-field="notification_pill_color_regular">
											<label for="notification_pill_color_regular" class="input-group-text fw-bolder text-muted settings-label">Regular pill color</label>
											<div class="d-flex gap-2 align-items-center w-100">
												<input name="notification_pill_color_regular" value="<?=htmlspecialchars((string)$regularVal, ENT_QUOTES, 'UTF-8')?>" type="text" class="form-control pill-color-text" id="notification_pill_color_regular" placeholder="#0d6efd or bg-primary">
												<input type="color" class="form-control form-control-color pill-color-picker" value="<?=htmlspecialchars((string)$regularVal, ENT_QUOTES, 'UTF-8')?>" title="Choose color" style="width:48px;padding:0;border:none;height:38px" />
												<span class="pill-color-preview rounded" style="display:inline-block;width:36px;height:24px;border:1px solid rgba(0,0,0,.08)"></span>
												<div class="ms-2 d-flex gap-1 palette">
													<?php foreach(['#0d6efd','#3b82f6','#0dcaf0','#20c997','#198754','#6610f2','#6f42c1','#adb5bd','#6c757d','#000000','#ffffff','#f8f9fa','#e9ecef','#495057','#212529','#ffc107'] as $c):?>
														<button type="button" class="btn btn-sm palette-swatch" data-color="<?=htmlspecialchars($c, ENT_QUOTES, 'UTF-8')?>" title="<?=htmlspecialchars($c)?>" style="background:<?=htmlspecialchars($c)?>;border:1px solid rgba(0,0,0,.08);width:22px;height:22px;padding:0"></button>
													<?php endforeach?>
													<button type="button" class="btn btn-sm btn-outline-secondary palette-info" data-category="regular" title="Show app pages for regular notifications" style="padding:.15rem .35rem"><i class="fa-solid fa-list"></i></button>
												</div>
											</div>
											<?php if(!empty($errors['notification_pill_color_regular'])):?>
												<small class="text-danger"><?=$errors['notification_pill_color_regular']?></small>
											<?php endif?>
										</div>

					<div class="mb-3 col-md-12 input-group">
					  <label for="notification_sound_upload" class="input-group-text fw-bolder text-muted settings-label">Upload sound</label>
					  <div class="sound-upload-control form-control">
						  <input name="notification_sound_upload" type="file" class="form-control" id="notification_sound_upload" accept=".mp3,.wav,.ogg,.m4a,.aac,audio/mpeg,audio/wav,audio/ogg,audio/mp4,audio/aac">
						  <span class="settings-help">Allowed: MP3, WAV, OGG, M4A, AAC. Max <?=function_exists('admin_settings_notification_sound_max_size_mb') ? admin_settings_notification_sound_max_size_mb() : 2?>MB.</span>
					  </div>
						<?php if(!empty($errors['notification_sound_upload'])):?>
						  <small class="text-danger"><?=$errors['notification_sound_upload']?></small>
						<?php endif?>
					</div>

					<div class="d-flex align-items-center gap-2 flex-wrap">
						<button type="button" class="btn btn-outline-primary" onclick="testSettingsNotificationSound()">
							<i class="fa-solid fa-volume-high"></i> Test sound
						</button>
						<span class="settings-help">Add or remove audio files in assets/sounds; this list updates automatically.</span>
					</div>
				</div>
			</div>

	 		<div class="progress my-1 d-none">
	 			<div class="progress-bar" style="width:0%">0%</div>
	 		</div>

			<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-4">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="text-decoration-none">
					<button type="button" class="btn btn-outline-secondary">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<button class="btn btn-primary">
					<i class="fa-solid fa-save"></i> Save settings
				</button>
			</div>
					</div>
				</div>
			</div>
		</form>

		<script>
			<?=get_app_image_cropper_inline_source()?>
			(function(){
				let key = 'admin_settings_active_tab';
				let tabs = document.querySelectorAll('#settings-tabs [data-bs-toggle="tab"]');
				if(!tabs || tabs.length === 0) return;
				function activateTab(btn){
					if(!btn) return;
					if(window.bootstrap && bootstrap.Tab){
						bootstrap.Tab.getOrCreateInstance(btn).show();
						return;
					}
					tabs.forEach(function(tab){
						let pane = document.querySelector(tab.getAttribute('data-bs-target') || '');
						let active = tab === btn;
						tab.classList.toggle('active', active);
						tab.setAttribute('aria-selected', active ? 'true' : 'false');
						if(pane){
							pane.classList.toggle('show', active);
							pane.classList.toggle('active', active);
						}
					});
				}
				let stored = localStorage.getItem(key);
				if(stored){
					let btn = document.getElementById(stored);
					if(btn){
						activateTab(btn);
					}
				}
				tabs.forEach(function(t){
					t.addEventListener('shown.bs.tab', function(e){
						localStorage.setItem(key, e.target.id);
					});
					t.addEventListener('click', function(e){
						localStorage.setItem(key, e.currentTarget.id);
						if(!window.bootstrap || !bootstrap.Tab){
							activateTab(e.currentTarget);
						}
					});
				});
			})();

				// Palette controls for notification pill colors
				(function(){
					function isHex(v){ return /^#[0-9A-Fa-f]{6}$/.test((v||'').trim()); }
					function setup(container){
						const text = container.querySelector('.pill-color-text');
						const picker = container.querySelector('.pill-color-picker');
						const preview = container.querySelector('.pill-color-preview');
						const sw = container.querySelectorAll('.palette-swatch');
						if(!text) return;
						function hexToRgba(hex, a){
							try{
								const r = parseInt(hex.substr(1,2),16);
								const g = parseInt(hex.substr(3,2),16);
								const b = parseInt(hex.substr(5,2),16);
								return 'rgba('+r+','+g+','+b+','+ (a||1) +')';
							}catch(e){return ''}
						}

						function clearSelected(){ sw.forEach(s=>s.classList.remove('selected')); }
						function markMatchingSwatch(hex){
							clearSelected();
							if(!hex) { preview.classList.remove('selected'); preview.style.boxShadow=''; return; }
							let found = false;
							sw.forEach(function(s){ if(s.dataset.color && s.dataset.color.toLowerCase() === hex.toLowerCase()){ s.classList.add('selected'); found = true; } });
							if(found){ preview.classList.add('selected'); preview.style.boxShadow = '0 0 0 6px ' + hexToRgba(hex,0.14); }
							else { preview.classList.remove('selected'); preview.style.boxShadow = '0 0 0 6px rgba(13,110,253,0.12)'; }
						}

						function update(){
							const v = (text.value||'').trim();
							if(isHex(v)){
								try{ picker.value = v; }catch(e){}
								preview.style.background = v;
								preview.className = 'pill-color-preview rounded';
								preview.style.display = 'inline-block';
								markMatchingSwatch(v);
							} else if(v !== ''){
								preview.style.background = '';
								preview.className = 'pill-color-preview rounded ' + v;
								preview.style.display = 'inline-block';
								clearSelected();
								preview.style.boxShadow = '0 0 0 4px rgba(0,0,0,0.06)';
							} else {
								preview.style.background = '';
								preview.className = 'pill-color-preview rounded';
								clearSelected();
								preview.style.boxShadow = '';
							}
						}

						picker.addEventListener('input', function(){ text.value = picker.value; update(); });
						text.addEventListener('input', update);
						sw.forEach(function(b){ b.addEventListener('click', function(){ text.value = b.dataset.color; try{ picker.value = b.dataset.color; }catch(e){} update(); }); b.addEventListener('mouseenter', function(){ b.classList.add('hovered'); }); b.addEventListener('mouseleave', function(){ b.classList.remove('hovered'); }); });
						update();
					}

					
					document.querySelectorAll('.notification-palette-control').forEach(setup);
				})();
		</script>

		<div class="modal fade" id="paletteNotificationsModal" tabindex="-1" aria-hidden="true">
			<div class="modal-dialog modal-lg modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Notifications for <span id="paletteModalColor"></span></h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<div id="paletteModalBody">
							<div class="text-center text-muted">Loading…</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>

		<script>
			(function(){
				function showPaletteModal(color, html){
					var titleEl = document.getElementById('paletteModalColor');
					var bodyEl = document.getElementById('paletteModalBody');
					if(titleEl) titleEl.textContent = color;
					if(bodyEl) bodyEl.innerHTML = html || '<div class="text-center text-muted">No notifications found.</div>';
					var modalEl = document.getElementById('paletteNotificationsModal');
					if(modalEl){ var m = new bootstrap.Modal(modalEl); m.show(); }
				}
				var palettePages = {
					serious: [
						{title:'Failed transaction alerts', plugin:'Payments', desc:'Critical payment failures, declined transfers and retried settlements.'},
						{title:'Security & account alerts', plugin:'Authentication', desc:'Unauthorized access warnings, account lockouts and suspicious logins.'},
						{title:'Chargebacks & reversals', plugin:'Reversals', desc:'Urgent dispute notices and refund processing actions.'},
						{title:'System outages', plugin:'System', desc:'Platform failures, downtime alerts and emergency maintenance notices.'}
					],
					mild: [
						{title:'Informational updates', plugin:'Notifications', desc:'Low priority system updates, reminders and status messages.'},
						{title:'Balance reminders', plugin:'Accounts', desc:'Low balance alerts and upcoming payment reminders.'},
						{title:'Verification prompts', plugin:'Security', desc:'Account verification guidance and optional security notices.'},
						{title:'Maintenance notices', plugin:'System', desc:'Scheduled maintenance and non-urgent service updates.'}
					],
					regular: [
						{title:'General notifications', plugin:'Notifications', desc:'Routine account messages, system confirmations and status updates.'},
						{title:'Feature updates', plugin:'Announcements', desc:'New feature releases and general app announcements.'},
						{title:'Receipts & summaries', plugin:'Reports', desc:'Payment receipts, transaction summaries and audit alerts.'},
						{title:'User activity', plugin:'Users', desc:'Non-critical user operations, profile changes and workflow progress.'}
					]
				};
				document.addEventListener('click', function(e){
					var t = e.target.closest && e.target.closest('.palette-info');
					if(!t) return;
					var category = t.getAttribute('data-category') || '';
					var control = t.closest('.notification-palette-control');
					var color = '';
					if(control){
						var textInput = control.querySelector('.pill-color-text');
						color = textInput ? (textInput.value || '').trim() : '';
					}
					showPaletteModal(color || category, '<div class="text-center text-muted">Loading…</div>');
					var body = document.getElementById('paletteModalBody');
					if(!body) return;
					var pages = palettePages[category] || [];
					if(pages.length === 0){
						body.innerHTML = '<div class="text-center text-muted">No app page list defined for this notification category.</div>';
						return;
					}
					var html = '<div class="list-group">';
					pages.forEach(function(item){
						html += '<div class="list-group-item p-3">';
						html += '<div class="d-flex justify-content-between align-items-start gap-3">';
						html += '<div><strong>' + item.title + '</strong>' + (item.desc ? '<div class="small text-muted mt-1">' + item.desc + '</div>' : '') + '</div>';
						html += '<span class="badge rounded-pill bg-secondary text-white">' + item.plugin + '</span>';
						html += '</div>';
						html += '</div>';
					});
					html += '</div>';
					body.innerHTML = html;
				});
			})();
		</script>

		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
		<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

		<script type="text/javascript">
			var valid_image = true;
			var image_added = false;
			var sound_added = false;
			var allowedSoundTypes = ['','audio/mpeg','audio/mp3','audio/wav','audio/x-wav','audio/ogg','audio/m4a','audio/aac','audio/mp4','application/ogg','application/octet-stream'];
			var allowedSoundExtensions = ['mp3','wav','ogg','m4a','aac'];
			var maxSoundBytes = <?=(function_exists('admin_settings_notification_sound_max_size_mb') ? admin_settings_notification_sound_max_size_mb() : 2)?> * 1024 * 1024;
			function swalTheme(){
				try{
					var theme = (document.documentElement && document.documentElement.getAttribute('data-bs-theme')) || '';
					if(!theme && document.body){ theme = document.body.getAttribute('data-bs-theme') || ''; }
					theme = String(theme || '').toLowerCase();
					var isDark = false;
					if(theme === 'dark'){
						isDark = true;
					}else if(theme === 'light'){
						isDark = false;
					}else if(theme === 'auto' || theme === ''){
						if(window.matchMedia){ isDark = window.matchMedia('(prefers-color-scheme: dark)').matches; }
					}
					return {
						popupClass: isDark ? 'bg-dark text-light' : 'bg-body text-body',
						bg: isDark ? '#0b1220' : '#ffffff',
						color: isDark ? '#f8fafc' : '#111827',
					};
				}catch(e){
					return { popupClass: 'bg-body text-body', bg: '#ffffff', color: '#111827' };
				}
			}
			function showSwal(opts){
				try{
					if(typeof Swal === 'undefined' || !Swal || !Swal.fire){
						try{ alert((opts && (opts.text || opts.html || opts.title)) || ''); }catch(ex){}
						return Promise.resolve({isConfirmed: true});
					}
					var t = swalTheme();
					return Swal.fire({
						icon: opts.icon || 'info',
						title: opts.title || '',
						html: opts.html || '',
						confirmButtonText: opts.confirmButtonText || 'OK',
						buttonsStyling: false,
						customClass: {
							confirmButton: opts.confirmButtonClass || 'btn btn-primary',
							popup: t.popupClass,
						},
						background: t.bg,
						color: t.color,
					});
				}catch(e){
					try{ alert((opts && (opts.text || opts.html || opts.title)) || ''); }catch(ex){}
					return Promise.resolve({isConfirmed: true});
				}
			}
			function testSettingsNotificationSound(){
				try{
					var enabled = document.getElementById('notification_sound_enabled');
					if(enabled && !enabled.checked){
						showSwal({icon: 'info', title: 'Notification sounds are muted', html: 'Enable notification sounds before testing.'});
						return;
					}

					var select = document.getElementById('notification_sound');
					var option = select && select.options ? select.options[select.selectedIndex] : null;
					var src = option ? option.getAttribute('data-sound-url') : '';
					var volumeInput = document.getElementById('notification_sound_volume');
					var volume = parseInt(volumeInput ? volumeInput.value : '90', 10);
					volume = isNaN(volume) ? 90 : Math.max(0, Math.min(100, volume));

					if(src){
						var audio = new Audio(src);
						audio.volume = volume / 100;
						audio.play().catch(function(){
							if(window.tormorPlayNotificationSound){
								window.tormorPlayNotificationSound('settings-preview-' + Date.now());
							}
						});
						return;
					}

					if(window.tormorPlayNotificationSound){
						window.tormorPlayNotificationSound('settings-preview-' + Date.now());
					}else{
						showSwal({icon: 'warning', title: 'No sound available', html: 'No notification sound file was found in assets/sounds.'});
					}
				}catch(e){
					showSwal({icon: 'error', title: 'Could not play sound', html: 'Please check that the selected sound file is reachable.'});
				}
			}
			function syncNotificationControls(){
				var enabled = document.getElementById('notification_sound_enabled');
				var enabledLabel = document.getElementById('notification_sound_enabled_label');
				if(enabled && enabledLabel){
					enabledLabel.textContent = enabled.checked ? 'Enabled' : 'Muted';
				}

				var volume = document.getElementById('notification_sound_volume');
				var volumeValue = document.getElementById('notification_sound_volume_value');
				if(volume && volumeValue){
					var value = parseInt(volume.value || '90', 10);
					value = isNaN(value) ? 90 : Math.max(0, Math.min(100, value));
					volume.value = value;
					volumeValue.textContent = value + '%';
				}
			}
			function validateNotificationSoundUpload(input){
				sound_added = false;
				if(!input || !input.files || !input.files[0]){
					return true;
				}

				var file = input.files[0];
				var extension = (file.name.split('.').pop() || '').toLowerCase();
				if(!allowedSoundExtensions.includes(extension) || !allowedSoundTypes.includes(file.type)){
					input.value = '';
					showSwal({
						icon: 'error',
						title: 'Invalid sound file',
						html: 'Allowed sound formats: ' + allowedSoundExtensions.join(', ').toUpperCase(),
					});
					return false;
				}

				if(file.size > maxSoundBytes){
					input.value = '';
					showSwal({
						icon: 'error',
						title: 'Sound file too large',
						html: 'Maximum notification sound size is <?=(function_exists('admin_settings_notification_sound_max_size_mb') ? admin_settings_notification_sound_max_size_mb() : 2)?>MB.',
					});
					return false;
				}

				sound_added = true;
				return true;
			}
			document.addEventListener('DOMContentLoaded', function(){
				var enabled = document.getElementById('notification_sound_enabled');
				var volume = document.getElementById('notification_sound_volume');
				var soundUpload = document.getElementById('notification_sound_upload');
				if(enabled){ enabled.addEventListener('change', syncNotificationControls); }
				if(volume){ volume.addEventListener('input', syncNotificationControls); }
				if(soundUpload){ soundUpload.addEventListener('change', function(){ validateNotificationSoundUpload(soundUpload); }); }
				syncNotificationControls();
			});

			function display_image(e)
			{
				const inputEl = e.currentTarget;
				if(window.AppImageCropper && typeof window.AppImageCropper.handleLegacyChange === 'function'){
					window.AppImageCropper.handleLegacyChange(e, {
						aspectRatio: 2,
						allowUncropped: true,
						maxBytes: window.AppImageCropper.MAX_BYTES,
						modalTitle: 'Preview CEO signature',
						errorTitle: 'Invalid image'
					}).then(function(result){
						valid_image = !result || !!result.ok || !!result.cancelled;
						image_added = !!(result && result.ok);
						if(!valid_image){
							showSwal({
								icon: 'error',
								title: 'Invalid image',
								html: 'Please choose a valid image not larger than 1MB.',
								confirmButtonText: 'OK',
								confirmButtonClass: 'btn btn-primary',
							});
						}
					});
					return;
				}

				let allowed = ['image/jpeg','image/png','image/webp'];
				let file = inputEl.files[0];

				if(!allowed.includes(file.type)){
					showSwal({
						icon: 'error',
						title: 'Invalid file type',
						html: 'Only files of this type allowed: ' + allowed.toString().replaceAll('image/',''),
						confirmButtonText: 'OK',
						confirmButtonClass: 'btn btn-primary',
					});
					valid_image = false;
					image_added = false;
					return;
				}
				valid_image = true;
				image_added = true;
				inputEl.parentNode.querySelector('img').src = URL.createObjectURL(file);
			}

			function submit_form(e)
			{
				if(!valid_image)
				{
					e.preventDefault()
					showSwal({
						icon: 'error',
						title: 'Invalid image',
						html: 'Please add a valid image',
						confirmButtonText: 'OK',
						confirmButtonClass: 'btn btn-primary',
					});
					return;
				}
			}

			const page = {

				uploading: false,

				submit: function(e)
				{
					e.preventDefault();

					if(page.uploading){
						showSwal({
							icon: 'info',
							title: 'Please wait',
							html: 'Please wait while we upload',
							confirmButtonText: 'OK',
							confirmButtonClass: 'btn btn-primary',
						});
						return;
					}

					let inputs = e.currentTarget.querySelectorAll("input,select,textarea");

				let progressWrap = document.querySelector('.progress');
				let prog = document.querySelector('.progress-bar');
				if(progressWrap && prog){
					prog.style.width = '0%';
					prog.innerHTML = 'Saving...' + '0%';
					progressWrap.classList.remove('d-none');
				}

					let myform = new FormData();
					for (var i = 0; i < inputs.length; i++) {

						if(inputs[i].type == 'file'){
							if(inputs[i].name === 'image' && image_added){
								myform.append(inputs[i].name,inputs[i].files[0]);
							}else if(inputs[i].name === 'notification_sound_upload' && inputs[i].files && inputs[i].files[0]){
								if(!validateNotificationSoundUpload(inputs[i])){
									return;
								}
								myform.append(inputs[i].name,inputs[i].files[0]);
							}
						}else if(inputs[i].type == 'checkbox' || inputs[i].type == 'radio'){
							if(inputs[i].checked)
								myform.append(inputs[i].name,inputs[i].value);
						}else{

							if(inputs[i].name == 'company_name' && inputs[i].value.trim() == ''){

								showSwal({
									icon: 'error',
									title: 'Company name required',
									html: 'A company name is required!',
									confirmButtonText: 'OK',
									confirmButtonClass: 'btn btn-primary',
								});
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

						let percent = 0;
						if(e && e.total){
							percent = Math.round((e.loaded / e.total) * 100);
						}
						let prog = document.querySelector('.progress-bar');
						if(!prog) return;
						prog.style.width = percent + '%';
						prog.innerHTML = 'Saving...' + percent + '%';
					});
	 
					let url = e.currentTarget.getAttribute('action') || '';
					xhr.open('post',url,true);
					xhr.send(myform);
				},

				handleResult: function(result)
				{
					console.log(result);
					let obj = JSON.parse(result);
					if(typeof obj == 'object')
					{
						let msg = (obj.message || '').toString();
						if(obj.success)
						{
							showSwal({
								icon: 'success',
								title: 'Saved',
								html: msg ? msg : 'Settings were saved successfully.',
								confirmButtonText: 'Continue',
								confirmButtonClass: 'btn btn-success',
							}).then(function(){
								window.location.href = '<?=ROOT?>/<?=$vars['admin_route']?>/<?=$vars['plugin_route']?>';
							});
						}else{
							let html = msg ? ('<div class="mb-2">' + msg + '</div>') : '';
							if(obj.errors && typeof obj.errors === 'object'){
								html += '<div style="text-align:left;">';
								for(let k in obj.errors){
									if(!Object.prototype.hasOwnProperty.call(obj.errors, k)) continue;
									let v = (obj.errors[k] || '').toString().trim();
									if(!v) continue;
									html += '<div>- ' + v.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</div>';
								}
								html += '</div>';
							}
							showSwal({
								icon: 'error',
								title: 'Could not save',
								html: html || 'Please check the form and try again.',
								confirmButtonText: 'OK',
								confirmButtonClass: 'btn btn-primary',
							});
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
			
			window.addEventListener('load',function(){

				$(document).ready(function() {
				  $('.summernote').summernote({
				  	placeholder: 'Your page content',
			        tabsize: 2,
			        height: 400
				  });
				});
			});

		</script>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>
