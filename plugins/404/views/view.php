<?php
render_app_error_screen(
	404,
	'We could not find that page',
	'The page may have moved or the address may be incorrect. Check the link and try again.',
	ROOT . '/home',
	'Return to home'
);
?>

<section class="app-error-page" aria-label="Search this application">
	<div class="app-error-panel" style="margin-top:0;padding:1.5rem">
		<h2 style="font-size:1rem;font-weight:700;margin:0 0 1rem">Looking for something?</h2>
		<form class="input-group mx-auto" style="max-width:500px" role="search">
			<input type="search" class="form-control" value="<?=old_value('find','','get')?>" name="find" aria-label="Search the application" placeholder="Search the application">
			<button class="btn btn-primary" type="submit">Search</button>
		</form>
		<?php if(!empty($_GET['find'])): ?>
			<div class="mt-3">
				<?php if(!empty($results)): ?>
					<?php do_action(plugin_id() . '_display_search_results', $results); ?>
				<?php else: ?>
					<p class="mb-0 text-body-secondary">No results found. Try a different search.</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>