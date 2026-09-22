<?php
session_start();

if (
    !isset($_SESSION['role']) ||
    (
        $_SESSION['role'] !== 'admin' &&
        $_SESSION['role'] !== 'client'
    )
) {
    header("Location: dashboard.php");
    exit;
}

$accountsDir = __DIR__ . '/json/';
$users = glob($accountsDir . '*.json');
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Social List</title>
    <link rel="stylesheet" href="css/global.css">
    <link rel="stylesheet" href="css/social-list.css">
</head>
<body>
    <div class="nav-bar">

        <div class="logo-icon-container">
            <a href="/">
                <img src="">
            </a>
        </div>

        <a href="/"class="logo-link">
            <div class="logo-container"><img src="  https://filamsoftware.com/project-estimate/images/logo-blue.svg"></div>
        </a>

        <div class="menu-con">



            <ul id="navItems" class="nav-items">
                <div class="menu-group" id="list_menu">

                    <a href="functions/logout.php"><li>Log Out</li></a>
                    
                </div>



                <div class="social-group">
                    <a href="https://www.linkedin.com/company/filamsoftware" _blank><div class="social in"></div></a>
                    <a href="https://www.facebook.com/filamsoftware" _blank><div class="social fb"></div></a>
                    <a href="https://twitter.com/FilAmSoftware" _blank><div class="social tw"></div></a>
                </div>

            </ul>

            <div id="burger" onclick="toggleBurger()">
                <div></div>
                <div></div>
                <div></div>
            </div>


            <div id="navbar_cta" class="nav-bar-cta" onclick="toggleformbox()">Schedule A Call</div>
        </div>
    </div>

    <section class="lists">
       <!-- TAB BUTTONS -->
       <div class="tabs">

        <?php if ($_SESSION['role'] === 'admin'): ?>
            <button class="tab-btn active" data-tab="usersTab">Users</button>
            <button class="tab-btn" data-tab="projectsTab">Projects</button>
        <?php else: ?>
            <button class="tab-btn active" data-tab="projectsTab">Projects</button>
        <?php endif; ?>

    </div>

<!-- TAB CONTENT -->
<div class="tab-content">

    <?php if ($_SESSION['role'] === 'admin'): ?>

<!-- USERS TAB -->
<div id="usersTab" class="tab-panel active">

    <button id="addUserBtn" class="btn btn-add">+ Add User</button>

    <div id="userContainer">
        <?php foreach ($users as $file): 
            $data = json_decode(file_get_contents($file), true);
            if (!$data) continue;

            $userId = basename($file, '.json');
            ?>
            <div class="row-grid user-card" data-id="<?php echo $userId; ?>">
                <div class="action-buttons">
                    <button class="view-btn select-item" data-id="<?php echo $userId; ?>">View</button>
                    <button class="edit-btn select-item" data-id="<?php echo $userId; ?>">Edit</button>
                    <button class="delete-btn select-item" data-id="<?php echo $userId; ?>">Delete</button>
                </div>

                <div class="col-grid">
                    <p class="userid">User ID: <?php echo $userId; ?></p>
                    <div class="user-details">
                        <p class="name"><?php echo $data['first_name'] . ' ' . $data['last_name']; ?> | </p>
                        <p class="email"><?php echo $data['email']; ?> | </p>
                        <p class="role">Role: <?php echo $data['role']; ?></p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="button-con">
        <button id="loadMoreBtn" style="display: block;">Load More</button>
    </div>

</div>

<?php endif; ?>


<!-- PROJECTS TAB -->
<div id="projectsTab" class="tab-panel <?php echo $_SESSION['role'] === 'client' ? 'active' : ''; ?>">

    <button id="addProjectBtn" class="btn btn-add">+ Add Project</button>

    <div id="projectContainer">
        <?php
        $projectsDir = __DIR__ . '/Projects/';
        if (!is_dir($projectsDir)) {
            mkdir($projectsDir, 0777, true);
        }

        $projects = glob($projectsDir . '*.json');

        foreach ($projects as $file):

            $data = json_decode(file_get_contents($file), true);
            if (!$data) continue;

    // CLIENT restriction
            if (
                $_SESSION['role'] === 'client' &&
                ($data['owner_id'] ?? '') !== $_SESSION['user_id']
            ) {
                continue;
            }

            $company = basename($file, '.json');
            ?>
            <div class="row-grid project-card" data-id="<?php echo $company; ?>">

                <div class="action-buttons">
                    <button class="view-project" data-id="<?php echo $company; ?>">View</button>
                    <button class="edit-project" data-id="<?php echo $company; ?>">Edit</button>
                    <button class="delete-project" data-id="<?php echo $company; ?>">Delete</button>
                </div>

                <div class="col-grid">
                    <p><strong><?php echo $company; ?></strong></p>
                    <p><?php echo $data['client_name']; ?> | <?php echo $data['email']; ?></p>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <div class="button-con">
        <button id="loadMoreProjects">Load More</button>
    </div>

</div>

</div>
</section>



<div id="userModal" class="modal">
  <div class="modal-content">

    <span class="close" id="closeModal">×</span>

    <h2 id="modalTitle">Add User</h2>

    <form id="userForm">
      <input type="hidden" name="mode" value="add">
      <input type="hidden" name="id" value="">

      <div class="form-group">
          <label></label>
      </div>

      <div class="form-group">
          <label>First Name</label>
          <input type="text" name="first_name" placeholder="First Name" required>
      </div>

      <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" placeholder="Last Name" required>
      </div>

      <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="Email" required>
      </div>

      <div class="form-group password-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Password">
      </div>



      <div class="form-group">
        <label>Role</label>
        <select name="role" required>
            <option value="viewer">Viewer</option>
            <option value="client">Client</option>
            <option value="admin">Admin</option>
        </select>
    </div>



    <button type="submit" id="saveBtn" class="btn">Save</button>

    <button type="button" id="resetPasswordBtn" class="btn btn-warning" style="display:none;">
        Send New Password
    </button>
</form>
</div>
</div>


<div id="deleteModal" class="modal">
  <div class="modal-content">
    <h3>Are you sure you want to delete?</h3>
    <div style="margin-top:20px;">
      <button id="confirmDelete" class="btn btn-danger">Yes</button>
      <button id="cancelDelete" class="btn">No</button>
  </div>
</div>
</div>


<div id="projectModal" class="modal">
  <div class="modal-content">

    <span class="close" id="closeProjectModal">×</span>

    <h2 id="projectModalTitle">Add Project</h2>



    <form id="projectForm">

        <input type="hidden" name="mode" value="add">
        <input type="hidden" name="id" value="">

        <div class="form-group">
            <label>Company Name</label>
            <input type="text" name="company_name" required>
        </div>

        <div class="form-group">
            <label>Client Name</label>
            <input type="text" name="client_name" required>
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required>
        </div>

        <hr>

        <div class="platform-tabs">
            <button type="button" class="platform-tab active" data-platform="all">All</button>
            <button type="button" class="platform-tab" data-platform="facebook">Facebook</button>
            <button type="button" class="platform-tab" data-platform="instagram">Instagram</button>
            <button type="button" class="platform-tab" data-platform="twitter">Twitter</button>
            <button type="button" class="platform-tab" data-platform="linkedin">LinkedIn</button>
            <button type="button" class="platform-tab" data-platform="youtube">YouTube</button>
            <button type="button" class="platform-tab" data-platform="tiktok">TikTok</button>
        </div>

        <h4>Keys</h4>

        <div class="platform-section" data-platform="facebook">

            <h4>Facebook</h4>

            <div class="form-group">
                <label>Facebook Page ID</label>
                <input type="text" name="fb_page_id">
            </div>

            <div class="form-group">
                <label>Facebook Access Token</label>
                <input type="text" name="fb_token">
            </div>

        </div>

        <div class="platform-section" data-platform="instagram">

            <h4>Instagram</h4>

            <div class="form-group">
                <label>Instagram ID</label>
                <input type="text" name="ig_id">
            </div>

            <div class="form-group">
                <label>Instagram Access Token</label>
                <input type="text" name="ig_token">
            </div>

        </div>
        
        <div class="platform-section" data-platform="twitter">

            <h4>Twitter (X)</h4>

            <div class="form-group">
                <label>Twitter Username / ID</label>
                <input type="text" name="tw_id">
            </div>

            <div class="form-group">
                <label>Twitter Bearer Token</label>
                <input type="text" name="tw_token">
            </div>

        </div>


        <div class="platform-section" data-platform="linkedin">

            <h4>LinkedIn</h4>

            <div class="form-group">
                <label>Organization ID</label>
                <input type="text" name="li_id">
            </div>

            <div class="form-group">
                <label>Access Token</label>
                <input type="text" name="li_token">
            </div>

        </div>


        <div class="platform-section" data-platform="youtube">

            <h4>YouTube</h4>

            <div class="form-group">
                <label>Channel ID</label>
                <input type="text" name="yt_channel_id">
            </div>

            <div class="form-group">
                <label>API Key</label>
                <input type="text" name="yt_api_key">
            </div>

        </div>

        <div class="platform-section" data-platform="tiktok">

            <h4>TikTok</h4>

            <div class="form-group">
                <label>Open ID / Username</label>
                <input type="text" name="tt_id">
            </div>

            <div class="form-group">
                <label>Access Token</label>
                <input type="text" name="tt_token">
            </div>

        </div>

        <div class="form-group">
            <label>Viewer Access</label>

            <select id="viewerSelect">
                <option value="">-- Select Viewer --</option>

                <?php
                foreach ($users as $file):
                    $data = json_decode(file_get_contents($file), true);
                    if ($data['role'] === 'viewer'):
                        $uid = basename($file, '.json');
                        ?>
                        <option value="<?php echo $uid; ?>">
                            <?php echo $data['first_name'] . ' ' . $data['last_name']; ?>
                            (<?php echo $data['email']; ?>)
                        </option>
                    <?php endif; endforeach; ?>
                </select>

                <!-- Hidden input that will actually submit -->
                <div id="selectedViewers"></div>
            </div>

            <button type="submit" class="btn">Save</button>

        </form>

    </div>
</div>


<script src="js/social-list.js"></script>
</body>
</html>