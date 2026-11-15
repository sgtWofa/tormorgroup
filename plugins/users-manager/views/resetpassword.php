
<div class="container-fluid border col-lg-5 col-md-6 mt-5 p-4" >

    <?php if (!empty($row)): ?>

        <form method="post">
            <center>
                <?=csrf()?>
                <h3><i class="fa fa-unlock"></i> RESET PASSWORD</h3>
                <div class="alert alert-primary text-center">Are You Sure You want to RESET This User's PASSWORD??</div>
            </center>		

            <div class="mb-3">
                <label for="exampleFormControlInput1" class="form-label">Username</label>
                <div class="form-control"><?= esc($row->first_name .' '. $row->last_name) ?></div>
            </div>

            <div class="mb-3">
                <label for="exampleFormControlInput1" class="form-label">Email address</label>
                <div class="form-control"><?= esc($row->email) ?></div>
            </div>
            <br>
            <input type="hidden" name="password"/>
            <button onclick="return confirm('Confirm to RESET PASSWORD')" class="btn btn-primary text-white float-end"><i class="fa fa-unlock"></i> RESET</button>
            <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-danger ">
                <i class="fa-solid fa-chevron-left"></i> Back
            </a>
        </form>
    <?php else: ?>
        <div class="alert alert-danger text-center">Record not found</div>             
        <a href="<?=ROOT?>/<?=$admin_route?>/<?=$plugin_route?>" class="btn btn-danger">
            <i class="fa fa-ban"></i> Cancel
        </a>
    <?php endif; ?>
</div>

