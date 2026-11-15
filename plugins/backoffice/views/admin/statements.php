
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
	</style>

<div class="table-responsive">
  <h3 class="text-center">ACCOUNTS STATEMENTS</h3>
	<table class="table table-striped table-hover">
		<thead>
			<tr>
				<th>#</th>
				<th>AGENT </th>
				<th style="text-align:right;">RECEIPT</th>
				<th style="text-align:right;">PAYMENT</th>
				<th style="text-align:right;">BALANCE</th>
        <th style="text-align:right;"></th>
			</tr>
		</thead>
		<tbody id="activityTable">
		<?php if(!empty($statements)): $id = 0; ?>
			<?php foreach($statements as $row): $id ++;?>
				<tr>
					<td><span class="badge bg-warning text-black rounded-circle pill"><?=$id?></span></td>
					<td>
						<a data-bs-toggle="modal" data-bs-target="#staticBackdrop<?= $row->id ?>" href="#">
							<span><img src="<?=get_image($row->assigned_image)?>" style="width: 30px; height: 30px; object-fit: cover; border-radius: 50%;"></span>
              <?=esc($row->assigned_first_name . ' ' . $row->assigned_last_name )?>
						</a>
					</td>
          <td style="text-align:right;"><?=number_format($row->total_credit ?? 0, 2)?></td>
					<td style="text-align:right;"><?=number_format($row->total_debit ?? 0, 2)?></td>
          <td style="text-align:right;"><?=number_format($row->total_balance ?? 0, 2)?></td>
          <td>
            <label class=" btn btn-warning" data-bs-toggle="modal" data-bs-target="#staticBackdrop<?=$row->id?>"><i class="fas fa-eye"></i> Details</label>
              <?php 
                  // Pass the payment ID to the modal for fetching data
                  $rowId = $row->assigned;
                  require plugin_path('views/admin/statement_modal.php'); 
              ?>
          </td>
				</tr>
			<?php endforeach?>
		<?php else:?>
			<tr><td colspan="6" class="text-center justify-content-center">No records to show!</td></tr>
		<?php endif?>
		</tbody>
		<tfoot>			
			<tr>
				<th></th>
				<th >TOTAL</th>
        <th style="text-align:right;"><?=number_format($totalStatementCredit ?? 0, 2)?></th>
				<th style="text-align:right;"><?=number_format($totalStatementDebit ?? 0, 2)?></th>
        <th style="text-align:right;"><?=number_format($totalStatementBalance ?? 0, 2)?></th>
        <th style="text-align:right;"></th>
			</tr>
		</tfoot>
	</table>
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
        const searchInput = document.getElementById("searchInput");
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
