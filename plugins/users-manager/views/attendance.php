<!-- Modal Trigger Button -->
<label type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#staticBackdrop"> <i class="fa-solid fa-calculator"></i></label>
<!-- Modal -->
<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <!-- Modal Header -->
      <div class="modal-header">
        <h5 class="modal-title" id="staticBackdropLabel">Currency Notes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body table-responsive">
        <table class="table table-hover table-sm">
          <thead>
            <tr>
              <th>NOTES</th>
              <th>COUNT</th>
              <th>VOLUME</th>
            </tr>
          </thead>
          <tbody>
            <tr data-denomination="200">
              <td class="px-5">GH&cent;200</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
            </tr>
            <tr data-denomination="100">
              <td class="px-5">GH&cent;100</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Volume" readonly></td>
            </tr>
             <tr data-denomination="50">
              <td class="px-5">GH&cent;50</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>
            <tr data-denomination="20">
              <td class="px-5">GH&cent;20</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>
            <tr data-denomination="10">
              <td class="px-5">GH&cent;10</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>
            <tr data-denomination="5">
              <td class="px-5">GH&cent;5</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>
            <tr data-denomination="2">
              <td class="px-5">GH&cent;2</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>
            <tr data-denomination="1">
              <td class="px-5">GH&cent;1</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>
            <tr>
              <td colspan="3">COINS</td>
            </tr>
            <tr data-denomination="2">
              <td class="px-5">GH&cent;2</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>

            <tr data-denomination="1">
              <td class="px-5">GH&cent;1</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>

            <tr data-denomination="0.5">
              <td class="px-5">50p</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>

            <tr data-denomination="0.2">
              <td class="px-5">20p</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>

            <tr data-denomination="0.1">
              <td class="px-5">10p</td>
              <td><input type="number" class="form-control p-1 count-input" placeholder="Enter count"></td>
              <td><input type="text" class="form-control p-1 volume-input" placeholder="Enter volume" readonly></td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <th class="px-5">TOTAL</th>
              <th><input type="text" class="form-control p-1" id="total-count" style="border:none; " placeholder="Total count" readonly></th>
              <th><input type="text" class="form-control p-1" id="total-volume" style="border:none; " placeholder="Total volume" readonly></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Modal Footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary submit">Understood</button>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript to Calculate Volumes, Totals, and Hide Modal -->
<script>
  document.addEventListener("DOMContentLoaded", function () {
    const countInputs = document.querySelectorAll(".count-input");
    const totalCountField = document.getElementById("total-count");
    const totalVolumeField = document.getElementById("total-volume");
    const submitBtn = document.querySelector('.submit');
    const staticBackdropModal = new bootstrap.Modal(document.getElementById('staticBackdrop'));

    countInputs.forEach(input => {
      input.addEventListener("input", updateVolumesAndTotals);
    });

    function updateVolumesAndTotals() {
      let totalCount = 0;
      let totalVolume = 0;

      countInputs.forEach(input => {
        const row = input.closest("tr");
        const denomination = parseFloat(row.getAttribute("data-denomination"));
        const count = parseFloat(input.value) || 0;
        const volumeField = row.querySelector(".volume-input");

        // Calculate volume for this row
        const volume = denomination * count;
        volumeField.value = volume.toFixed(2);

        // Accumulate totals
        totalCount += count;
        totalVolume += volume;
      });

      // Update total count and volume fields
      totalCountField.value = totalCount;
      totalVolumeField.value = totalVolume.toFixed(2);
    }

    // Update the event listener for the submit button
    submitBtn.addEventListener('click', function() {
      // Show totals in an alert or replace with your preferred display method
      alert(`Total Count: ${totalCountField.value}\nTotal Volume: GHS ${totalVolumeField.value}`);
      
      // Hide the modal
      staticBackdropModal.hide();
    });
  });
</script>
