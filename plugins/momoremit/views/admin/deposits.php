<?php if(user_can('view_momoremit')):?>

<style type="text/css">
	tbody tr{
		white-space: nowrap;
	}
</style>

<div class="row my-3 mx-auto" >
	<form class="input-group col-10 d-flex mb-3" >
	  <input placeholder="Search by Name" type="text" class="form-control" value="<?=old_value('find','','get')?>" name="find" autofocus="true">
	  <button class="input-group-text bg-warning text-white" id="basic-addon1">
	  	Search
	  </button>
	</form>
	<div class="input-group">
	
	<!-- dropdown -->
	<div class="dropdown">
        <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
            Transactions
        </button>
        <?php
            $session = get_session();
            $userId = $session->id;

        ?>
        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">
		    <?php if(user_can("view_momoremit")):?>
		        <li>
		            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
		                <i class="fa fa-list text-muted"></i> All transactions
		            </a>
		        </li>
		        <li>
		            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/deposits">
		                <i class="fa fa-arrow-down text-muted"></i> Deposits
		            </a>
		        </li>
		        <li>
		            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/withdrawals">
		                <i class="fa fa-arrow-up text-muted"></i> Withdrawals
		            </a>
		        </li>
		        <li>
		            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/sendings">
		                <i class="fa fa-paper-plane text-muted"></i> Sending
		            </a>
		        </li>
		        <li>
		            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/cits">
		                <i class="fa fa-user-edit text-muted"></i> CITs
		            </a>
		        </li>
		    <?php endif?>
		</ul>
    </div>
    
    <span class="mx-2">
		<div class="dropdown">
		    <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
		        <i class="fa-solid fa-plus"></i> Add
		    </button>
		    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">
		        <?php if(user_can("view_momoremit")):?>
		            <li>
				            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/withdrawal">
				                <i class="fa fa-arrow-up text-warning"></i> Withdrawal
				            </a>
				        </li>
				        <li>
				            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/sending">
				                <i class="fa fa-paper-plane text-warning"></i> Sending
				            </a>
				        </li>
				        <li>
				            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/cit">
				                <i class="fa fa-user-edit text-warning"></i> CIT
				            </a>
				        </li>
		        <?php endif?>
		    </ul>
		</div>
	</span>
	<center class="mx-auto text-center"><h2>DEPOSIT TRANSACTIONS</h2></center>
	</div>
</div>
<div class="table-responsive">
	

	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th class="bg-secondary text-warning">#</th>
				<th class="bg-secondary text-warning">Transaction type</th>
				<th class="bg-secondary text-warning">Category</th>
				<th class="bg-secondary text-warning">Amount</th>
				<th class="bg-secondary text-warning">Charge</th>
				<th class="bg-secondary text-warning">Receiving wallet</th>
				<th class="bg-secondary text-warning">Paying wallet</th>
				<th class="bg-secondary text-warning">Date created</th>
				<th class="bg-secondary text-warning">Created by</th>
				<th class="bg-secondary text-warning"></th>
			</tr>
		</thead>

		<?php if(!empty($rows)): $id = 0;?>
			<tbody id="activityTable">
			<?php foreach($rows as $row): $id ++;?>
				<tr>
					<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span><?=$row->id?></td>
					<td>
						<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
							<?=esc($row->network . ' (' . $row->transaction_type . ')')?>
						</a>
					</td>
					<td><?=esc($row->phone)?></td>
					<td><?=number_format($row->amount ?? 0, 2)?></td>
					<td><?=number_format($row->charge ?? 0, 2)?></td>
					<td><?=esc($row->in_wallet_name ?? '')?> (<?=esc($row->in_wallet_network ?? '')?>)</td>
					<td><?=esc($row->out_wallet_name ?? '')?> (<?=esc($row->out_wallet_network ?? '')?>)</td>
					<td><?=get_date_time($row->date_created)?></td>
					<td>
						<span><img src="<?=get_image($row->user_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
						 <?=$row->user_first_name . ' ' . $row->user_last_name ?>
					</td>

					<td>
						<?php if(user_can('view_category_details')):?>
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
							<button class="btn btn-primary btn-sm" title="View">
								<i class="fa-solid fa-eye"></i> 
							</button>
						</a>
						<?php endif?>

						<!-- <?php if(user_can('edit_category')):?>
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
							<button class="btn btn-warning btn-sm" title="Edit">
								<i class="fa-solid fa-pen-to-square"></i>
							</button>
						</a>
						<?php endif?>

						<?php if(user_can('delete_category')):?>
						<a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/delete/<?=$row->id?>">
							<button class="btn btn-danger btn-sm" title="Delete">
								<i class="fa-solid fa-trash"></i>
							</button>
						</a>
						<?php endif?> -->
					</td>
				</tr>
			<?php endforeach?>
		<?php else:?>
				<tr>
					<td colspan="10" class="text-center justify-content-center"> No records to show!</td>
				</tr>
			</tbody>	
		<?php endif?>						
			<tfoot>
				<tr>
					<th class="bg-secondary text-warning"></th>
					<th class="bg-secondary text-warning">TOTAL : </th>
					<th class="bg-secondary text-warning"></th>
					<th class="bg-secondary text-warning"><?=number_format($total_amount ?? 0 , 2)?></th>
					<th class="bg-secondary text-warning"><?=number_format($total_charge ?? 0 , 2)?></th>
					<th class="bg-secondary text-warning"></th>
					<th class="bg-secondary text-warning"></th>
					<th class="bg-secondary text-warning"></th>
					<th class="bg-secondary text-warning"></th>
					<th class="bg-secondary text-warning"></th>
				</tr>
			</tfoot>
	</table>

	<!-- <?=$pager->display()?> -->
	<!-- Total Pages Display -->
    <p id="totalPagesText" class="text-center mt-3"></p>

    <!-- Pagination -->
    <nav id="paginationNav" aria-label="Page navigation" class="mt-1">
      <ul class="pagination justify-content-center" style="font-size: 8px;"></ul>
    </nav>

    <!-- Scripts -->
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        const rowsPerPage = 10;
        const table = document.getElementById("activityTable");
        const paginationNav = document.querySelector("#paginationNav .pagination");
        const totalPagesText = document.getElementById("totalPagesText");

        let currentPage = 1;
        let originalRows = Array.from(table.rows); // Store the original rows for filtering
        let filteredRows = [...originalRows]; // To hold filtered rows

        // Function to display the current page
        function displayPage(page) {
          const start = (page - 1) * rowsPerPage;
          const end = start + rowsPerPage;

          // Hide all rows initially
          filteredRows.forEach((row, index) => {
            row.style.display = index >= start && index < end ? "" : "none";
          });

          currentPage = page;
          updatePagination();
        }

        // Function to update pagination controls
        function updatePagination() {
          paginationNav.innerHTML = "";
          const totalPages = Math.ceil(filteredRows.length / rowsPerPage);

          // Update total pages display
          totalPagesText.textContent = `Total Pages: ${totalPages}`;

          // Generate pagination range
          const paginationRange = getPaginationRange(currentPage, totalPages);

          // Render pagination navigation with ellipsis
          paginationRange.forEach((item) => {
            const li = document.createElement("li");
            li.className = "page-item" + (item === currentPage ? " active" : "");
            const a = document.createElement("a");
            a.className = "page-link";

            if (item === "...") {
              li.classList.add("disabled");
              a.textContent = "...";
            } else {
              a.textContent = item;
              a.href = "#";
              a.addEventListener("click", (e) => {
                e.preventDefault();
                if (typeof item === "number") displayPage(item);
              });
            }

            li.appendChild(a);
            paginationNav.appendChild(li);
          });
        }

        // Function to calculate pagination range with ellipsis
        function getPaginationRange(current, total) {
          const range = [];
          const maxVisiblePages = 10;

          if (total <= maxVisiblePages) {
            // Show all pages if total <= maxVisiblePages
            for (let i = 1; i <= total; i++) range.push(i);
          } else {
            range.push(1); // Always include the first page

            const start = Math.max(2, current - 1); // Start from one page before the current page
            for (let i = start; i < Math.min(start + 3, total - 1); i++) {
              range.push(i);
            }

            if (current < total - 3) range.push("..."); // Add ellipsis if necessary

            range.push(total - 1); // Include the second-to-last page
            range.push(total); // Include the last page
          }

          return range;
        }

        // Initialize the table with the first page
        displayPage(1);
      });
    </script>

</div>
<?php else:?>
	<div class="alert alert-danger text-center">
		Access denied. You dont have permission for this action
	</div>
<?php endif?>