<?php if(user_can('view_cfa')):?>
<style type="text/css">
		tbody tr{
			white-space: nowrap;
		}
		thead tr{
			white-space: nowrap;
		}
		tfoot tr{
			white-space: nowrap;
		}

		td.wrap{
			white-space: normal;
		}
	</style>

<div class="row my-3 mx-auto">
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
			    <?php if(user_can("view_cfa")):?>
			        <li>
			            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>">
			                <i class="fa fa-list text-warning"></i> All transactions
			            </a>
			        </li>
			        <li>
			            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/buys">
			                <i class="fa fa-arrow-down text-warning"></i> Buy
			            </a>
			        </li>
			        <li>
			            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/sells">
			                <i class="fa fa-arrow-up text-warning"></i> Sell
			            </a>
			        </li>
			    <?php endif?>
			</ul>
	    </div>
	    <span class="mx-2">
					<div class="dropdown">
					    <button class="btn btn-sm btn-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
					        <i class="fa-solid fa-plus"></i> New
					    </button>
					    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">
					        <?php if(user_can("view_momoremit")):?>
					            <li>
							            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/buy">
							                <i class="fa fa-arrow-circle-down text-warning"></i> Buy
							            </a>
							        </li>
							        <li>
							            <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/sell">
							                <i class="fa fa-arrow-circle-up text-warning"></i> Sell
							            </a>
							        </li>
					        <?php endif?>
					    </ul>
					</div>
			</span>
		<center class="mx-auto text-center"><h2>BUY TRANSACTIONS</h2></center>
	</div>
</div>
<div class="table-responsive">
	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th class="bg-secondary text-warning">#</th>
				<th class="bg-secondary text-warning">Date created</th>
				<th class="bg-secondary text-warning">Description</th>
				<th class="bg-secondary text-warning">Transaction type</th>
				<th class="bg-secondary text-warning">Status</th>
				<th class="bg-secondary text-warning" style="text-align:right;">Amount (CFA)</th>
				<th class="bg-secondary text-warning" style="text-align:right;">Amount (GH&cent;)</th>
				<th class="bg-secondary text-warning">Created by</th>
				<th class="bg-secondary text-warning">Action</th>
			</tr>
		</thead>
		<tbody id="activityTable">
		<?php if(!empty($rows)): $id = 0; 
			$session = get_session();
			$username = $session->first_name . ' '. $session->last_name;
			$user_id = $session->id;
			//dd($user_id);
			?>
			<?php foreach($rows as $row): $id ++;?>
				<tr>
					<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span><?=$row->id?></td>
					<td class="wrap">
						<a style="text-decoration: none;" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
							<?=get_date_time($row->date_created)?>
						</a>
					</td>
					<td class="wrap"><?=esc($row->description ?? '')?></td>
					<td><?=esc($row->transaction_type ?? '')?></td>
					<td>
						<?php
								// Determine badge class based on transaction status
								$badgeClass = '';
								switch (strtolower($row->transaction_status ?? 'complete')) {
									case 'pending':
										$badgeClass = 'badge bg-warning text-dark';
										break;
									case 'complete':
										$badgeClass = 'badge bg-success';
										break;
									case 'rejected':
										$badgeClass = 'badge bg-danger';
										break;
									default:
										$badgeClass = 'badge bg-secondary';
										break;
								}
							?>
						<span class="<?= $badgeClass ?>"><?=esc($row->transaction_status)?></span>
					</td>
					<td style="text-align:right;"><?=number_format($row->amount_cfa ?? 0, 2)?></td>
					<td style="text-align:right;"><?=number_format($row->amount_ghs ?? 0, 2)?></td>
					<td>
						<span><img src="<?=get_image($row->user_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
						 <?=$row->user_first_name . ' ' . $row->user_last_name ?>
					</td>

					<td>
						<div class="dropdown">
                            <button class="btn btn-sm btn-outline-warning dropdown-toggle" type="button" id="dropdownMenuButton<?=$id?>" data-bs-toggle="dropdown" aria-expanded="false" style="border: none;">
                            	<i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?=$id?>">
                                <?php if(user_can("view_cfa") || $user_id === $row->created_by):?>
                                    <li>
                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/view/<?=$row->id?>">
                                            <i class="fa fa-eye text-warning"></i> View
                                        </a>
                                    </li>
                                <?php endif;?>
                                <?php if(strtolower($row->transaction_status) === 'draft' || strtolower($row->transaction_status) === 'pending' ):?>
                                <?php if(user_can("edit_cfa") || $user_id === $row->created_by):?>
                                    <li>
                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/edit/<?=$row->id?>">
                                            <i class="fa fa-pen text-warning"></i> Update
                                        </a>
                                    </li>
                                <?php endif;?>
                                <?php if(user_can("delete_cfa") || $user_id === $row->created_by):?>
                                    <li>
                                        <a class="dropdown-item" href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>/resetpassword/<?=$row->id?>">
                                            <i class="fa fa-trash text-warning"></i> Delete
                                        </a>
                                    </li>
                                <?php endif;?>
                                <?php endif;?>
                            </ul>
                        </div>
					</td>
				</tr>
			<?php endforeach?>
		<?php else:?>
			<tr>
				<td colspan="9" class="text-center">No records to show!</td>
			</tr>
		<?php endif?>
		</tbody>
		<tfoot>			
			<tr>
				<th colspan="2" class="bg-secondary text-warning text-center">TOTAL : </th>
				<th class="bg-secondary text-warning"></th>
				<th class="bg-secondary text-warning"></th>
				<th class="bg-secondary text-warning"></th>
				<th class="bg-secondary text-warning" style="text-align:right;"><span class="badge bg-warning text-black">CFA</span> <?=number_format($total_amount_cfa ?? 0, 2)?></th>
				<th class="bg-secondary text-warning" style="text-align:right;"><span class="badge bg-warning text-black">GH&cent;</span> <?=number_format($total_amount_ghs ?? 0, 2)?></th>
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