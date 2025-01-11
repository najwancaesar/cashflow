
<?php
include "includes/koneksi.php";
if(isset($_GET['act'])){
	if($_GET['act'] == 't'){
        $carikode = mysqli_query($con, "SELECT id_user from user order by id_user Desc limit 1");
        $datakode = mysqli_fetch_array($carikode);
        // jika $datakode
        if ($datakode) {
        $nilaikode = (int) substr($datakode['id_user'],3,3);
        $kode = $nilaikode + 1;
        $kode_otomatis = "U".str_pad($kode, 3, "0", STR_PAD_LEFT);
        } else {
        $kode_otomatis = "U001";
        }
    ?>
    <div class="data-table-area mg-b-15" style="min-height: 40em;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-8">
                    <div class="sparkline12-list shadow-reset mg-t-30">
                        <div class="sparkline12-hd">
                            <div class="main-sparkline12-hd">
                                <h1>Tambah Data User</h1>
                            </div>
                        </div>
                        <div class="sparkline12-graph">
                            <div class="basic-login-form-ad">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="all-form-element-inner">
                                            <form method="POST" action="aksi_user.php?act=t">
                                                <div class="form-group-inner">
                                                    <div class="row">
                                                        <div class="col-lg-3">
                                                            <label class="login2 pull-right pull-right-pro">Kode User</label>
                                                        </div>
                                                        <div class="col-lg-9">
                                                            <input type="text" class="form-control" name="kode_user" value="<?php echo $kode_otomatis ?>" readonly required />
                                                        </div>
                                                    </div>
                                                </div>
												<div class="form-group-inner">
                                                    <div class="row">
                                                        <div class="col-lg-3">
                                                            <label class="login2 pull-right pull-right-pro">Pemilik User</label>
                                                        </div>
                                                        <div class="col-lg-9">
                                                            <input type="text" class="form-control" name="nama" required/>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group-inner">
                                                    <div class="row">
                                                        <div class="col-lg-3">
                                                            <label class="login2 pull-right pull-right-pro">user name</label>
                                                        </div>
                                                        <div class="col-lg-9">
                                                            <input type="text" class="form-control" name="username" required/>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group-inner">
                                                    <div class="row">
                                                        <div class="col-lg-3">
                                                            <label class="login2 pull-right pull-right-pro">Password</label>
                                                        </div>
                                                        <div class="col-lg-9">
                                                            <input type="text" class="form-control" name="password" required/>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group-inner">
                                                    <div class="row">
                                                        <div class="col-lg-3">
                                                            <label class="login2 pull-right pull-right-pro">role</label>
                                                        </div>
                                                        <div class="col-lg-9">
                                                            <select class="form-control" name="role" required>
																<option value="">- Pilih role User -</option>
																<option value="Admin">Admin</option>
																<option value="Bidan">Bidan</option>
																<option value="Direktur">Direktur</option>
															</select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group-inner">
                                                    <div class="login-btn-inner">
                                                        <div class="row">
                                                            <div class="col-lg-3"></div>
                                                            <div class="col-lg-9">
                                                                <div class="login-horizental cancel-wp pull-left">
                                                                    <a href="main.php?module=pelayanan" class="btn btn-warning" type="submit"><i class='fa fa-rotate-left'></i> Kembali</a>

                                                                    <button class="btn btn-sm btn-primary login-submit-cs" type="submit"><i class='fa fa-save'></i> Simpan</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php 
    }
    if($_GET['act'] == 'e'){
    $q_pelayanan = mysqli_query($con, "select * from user  
    where id_user = '$_GET[id]'");
    $pelayanan = mysqli_fetch_array($q_pelayanan);
    ?>
    <div class="data-table-area mg-b-15" style="min-height: 40em;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-8">
                    <div class="sparkline12-list shadow-reset mg-t-30">
                        <div class="sparkline12-hd">
                            <div class="main-sparkline12-hd">
                                <h1>Edit Data User</h1>
                            </div>
                        </div>
                        <div class="sparkline12-graph">
                            <div class="basic-login-form-ad">
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="all-form-element-inner">
                                            <form method="POST" action="aksi_user.php?act=e">
                                                <div class="form-group-inner">
                                                    <div class="row">
                                                        <div class="col-lg-3">
                                                            <label class="login2 pull-right pull-right-pro">Kode User</label>
                                                        </div>
                                                        <div class="col-lg-9">
                                                            <input type="text" class="form-control" name="kode_user" value="<?php echo $pelayanan['kode_user'] ?>" readonly required/>
                                                        </div>
                                                    </div>
                                                </div>
												 <div class="form-group-inner">
                                                    <div class="row">
                                                        <div class="col-lg-3">
                                                            <label class="login2 pull-right pull-right-pro">Pemilik User</label>
                                                        </div>
                                                        <div class="col-lg-9">
                                                            <input type="text" class="form-control" name="nama" value="<?php echo $pelayanan['nama'] ?>" required/>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group-inner">
                                                    <div class="row">
                                                        <div class="col-lg-3">
                                                            <label class="login2 pull-right pull-right-pro">User Name</label>
                                                        </div>
                                                        <div class="col-lg-9">
                                                            <input type="text" class="form-control" name="username" value="<?php echo $pelayanan['username'] ?>" required/>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group-inner">
                                                    <div class="row">
                                                        <div class="col-lg-3">
                                                            <label class="login2 pull-right pull-right-pro">Password</label>
                                                        </div>
                                                        <div class="col-lg-9">
                                                            <input type="text" class="form-control" name="password" value="<?php echo $pelayanan['password'] ?>" required/>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group-inner">
                                                    <div class="row">
                                                        <div class="col-lg-3">
                                                            <label class="login2 pull-right pull-right-pro">role</label>
                                                        </div>
														<div class="col-lg-9">
                                                            <select class="form-control" name="role" required>
																<option value="<?php echo $pelayanan['role'] ?>"><?php echo $pelayanan['role'] ?></option>
																<option value="">- Pilih role User -</option>
																<option value="Admin">Admin</option>
																<option value="Bidan">Bidan</option>
																<option value="Direktur">Direktur</option>
															</select>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="form-group-inner">
                                                    <div class="login-btn-inner">
                                                        <div class="row">
                                                            <div class="col-lg-3"></div>
                                                            <div class="col-lg-9">
                                                                <div class="login-horizental cancel-wp pull-left">
                                                                    <a href="main.php?module=pelayanan" class="btn btn-warning" type="submit"><i class='fa fa-rotate-left'></i> Kembali</a>

                                                                    <button class="btn btn-sm btn-primary login-submit-cs" type="submit"><i class='fa fa-save'></i> Simpan</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php 
    }
}else{
?>

<div class="data-table-area mg-b-15" style="min-height: 40em;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="sparkline13-list shadow-reset">
                    <div class="sparkline13-hd">
                        <div class="main-sparkline13-hd">
                            <h1>Data User</h1>
                        </div>
                    </div>
                    <div class="sparkline13-graph">
                        <div class="datatable-dashv1-list custom-datatable-overright">
                            <div id="toolbar">
                                <a href="main.php?module=datauser&act=t" type="button" class="btn btn-custon-four btn-primary btn-md">
                                    <i class="fa fa-plus">Tambah</i>
                                </a>
                            </div>


                            <table id="table" data-toggle="table" data-pagination="true" data-search="true"  data-toolbar="#toolbar">
                                <thead>
                                    <tr>
                                        <th>Kode_User</th>
										<th>Pemilik User</th>
                                        <th>User Name</th>
                                        <th>role</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                    $sql = mysqli_query($con,"select * from user");
                                    $no=1;
                                    while($row=mysqli_fetch_array($sql)){
                                    ?>

                                    <tr>
                                    
                                    <td><?php echo $row['kode_user'] ?></td>
									<td><?php echo $row['nama'] ?></td>
									<td><?php echo $row['username'] ?></td>
                                    <td><?php echo $row['role'] ?></td>
                                    <td>
                                        <a href="main.php?module=datauser&act=e&id=<?php echo $row['kode_user'] ?>" class="btn btn-warning btn-sm" title="Edit" data-toggle="tooltip">
                                        <i class="fa fa-edit"></i>
                                        </a>
                                        
                                        <a href="aksi_user.php?&act=h&id=<?php echo $row['kode_user'] ?>" class="btn btn-danger btn-sm" title="Hapus" data-toggle="tooltip">
                                        <i class="fa fa-trash"></i></a>										
                                    </td>
                                    </tr>

                                    <?php 
                                    $no++;
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
}
?>
  