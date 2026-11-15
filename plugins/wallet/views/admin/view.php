<?php if(user_can('view_wallet_details')):?>
	<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
		tbody tr.wrap{
			white-space: normal;
		}

		td.wrap{
			white-space: normal;
		}
	</style>

	<?php if(!empty($row)): ?>

		<div class="row g-3 col-md-12 col-lg-11 mx-auto shadow p-4 rounded">
            <!-- Additional content below -->
            <center>
                <div class="badge">
                    <img src="<?=APP_LOGO2 ?? ''?>" style="cursor:pointer;width:100%;max-width:40px;max-height: 40px;object-fit: cover;">
                    <strong style="font-size:15px; color: gray;"> <?=APP_NAME?></strong>
                </div>
                <h4 class="text-center" style="font-weight: bolder;">
                    Wallet Statement
                </h4>
                <b class="text-warning fs-2"><?=$row->name ?? ''?></b><br>
                <small><?=$row->network ?? ''?> <?=$row->wallet_type ?? ''?> : <?=$row->wallet_number ?? ''?></small><br>
                <small><i><?=$row->description ?? ''?></i></small><br>
                <strong class="text-warning">Current Balance: <?=number_format($row->balance ?? 0, 2)?> </strong><br>
            </center>

				<div class="col col-md-12 d-flex table-responsive">
				    <table class="table table-striped table-hover border-bottom col-lg-6 col-md-12 table-sm" style="height: fit-content !important;">
				        <thead>
				            <tr>
				            	<th colspan="6" class="text-primary">TRANSACTION DETAILS</th>
				            	<th class="text-primary">
									<!-- dropdown aligned to the extreme right -->
						            <div class="d-flex justify-content-end">
						                <div class="dropdown">
						                    <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
						                        Sellect Wallet
						                    </button>
						                    <?php
						                        $session = get_session();
						                        $userId = $session->id;
						                    ?>
						                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton<?=$id?>">
						                        <?php if(user_can("view_wallet")):?>
						                        <?php if(!empty($wallets)):?>
						                        	<?php foreach($wallets as $wallet):?>
							                            <li>
							                                <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$wallet->id?>">
							                                    <i class="fa fa-wallet text-muted"></i> <?=$wallet->name ?? ''?> (<?=$wallet->network . ' ' . number_format($wallet->balance, 2 ?? '')?>)
							                                </a>
							                            </li>
						                        	<?php endforeach?>
						                        <?php endif?>
						                        <?php endif?>
						                    </ul>
						                </div>
						            </div>
				            	</th>
				            </tr>
				            <tr class="alert alert-warning">
				                <th class="bg-secondary text-warning">#</th>
				                <th class="bg-secondary text-warning">Date</th>
				                <th class="bg-secondary text-warning">Description</th>
				                <th class="bg-secondary text-warning" style="text-align:right;">In</th>
				                <th class="bg-secondary text-warning" style="text-align:right;">Out</th>
				                <th class="bg-secondary text-warning" style="text-align:right;">Balance</th>
				                <th class="bg-secondary text-warning" style="text-align:right;">Created by</th>
				            </tr>
				        </thead>
				        <tbody>
				            <?php if (!empty($rows)): $id = 0;?>
				                <?php foreach ($rows as $rRow): $id++; ?>
				                    <?php if (isset($rRow->amount)): ?>
				                    <tr>
				                        <td><span class="badge bg-warning text-black rounded-circle pill"><?= $id ?></span> <?= $rRow->id ?? '' ?></td>
				                        <td class="wrap"><?= get_date_time($rRow->date_created, 2) ?></td>
				                        <td class="wrap"><?= esc($rRow->description ?? '') ?></td>
				                        <td style="text-align:right;"><?= number_format($rRow->credit ?? 0, 2) ?></td>
				                        <td style="text-align:right;"><?= number_format($rRow->debit ?? 0, 2) ?></td>
				                        <td style="text-align:right;"><?= number_format($rRow->balance ?? 0, 2) ?></td>
				                        <td style="text-align:right;">
				                            <span><img src="<?= get_image($rRow->image) ?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
				                            <?= esc($rRow->first_name ?? '') ?> <?= esc($rRow->last_name ?? '') ?>
				                        </td>
				                    </tr>
				                    <?php endif; ?>
				                <?php endforeach; ?>
				            <?php else: ?>
				                <tr><td colspan="7" class="text-center justify-content-center"><div>No records to show!</div></td></tr>
				            <?php endif; ?>
				        </tbody>
				        <tfoot>
				            <tr>
				                <th colspan="3" class="text-center bg-secondary text-warning">TOTAL</th>
				                <th class="bg-secondary text-warning" style="text-align:right;"><?= number_format($row->credit ?? 0, 2) ?></th>
				                <th class="bg-secondary text-warning" style="text-align:right;"><?= number_format($row->debit ?? 0, 2) ?></th>
				                <th class="bg-secondary text-warning" style="text-align:right;"><?= number_format($row->balance ?? 0, 2) ?></th>
				                <th class="bg-secondary text-warning"></th>
				            </tr>
				        </tfoot>
				    </table>
				</div>
				<?= $pager->display() ?>


			<div class="d-flex justify-content-between">
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
					<button class="btn btn-md btn-outline-primary" style="border:none;">
						<i class="fa-solid fa-chevron-left"></i> Back
					</button>
				</a>
				<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
					<button class="btn btn-md btn-outline-warning" style="border:none;">
						<i class="fa-solid fa-pen-to-square"></i> Edit
					</button>
				</a>
			</div>
		</div>

	<?php else:?>
		<div class="alert alert-danger text-center">
			That record was not found!
		</div>
		
		<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			<button class="btn btn-primary">Back</button>
		</a>
	<?php endif?>

<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You don't have permission for this action.
	</div>
<?php endif?>
