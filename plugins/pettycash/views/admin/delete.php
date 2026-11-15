<div class="card shadow-sm border-0 my-4">
	<div class="card-body">
		<h4 class="mb-2">Delete Petty Cash Transaction</h4>
		<p class="text-muted mb-3">This will remove <strong><?=esc($row->reference ?? '')?></strong> before it is acknowledged by the assignee.</p>
		<form method="post" class="d-flex gap-2">
			<?=csrf()?>
			<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>" class="btn btn-outline-secondary">Cancel</a>
			<button class="btn btn-danger">Delete Transaction</button>
		</form>
	</div>
</div>
