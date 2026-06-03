OK. After reviewing the code for the `driver_dashboard.php` and comparing it with the `operator_dashboard.php` code, here are the key differences.

**Key Differences in Orientation Schedule Queries:**

*   The query includes a clause WHERE os.target_role = 'operator', but this is incorrect as the query should fetch records based on the role it has.
*   There is an attendance status and is linked to this query `(SELECT is_completed FROM orientation_attendees WHERE orientation_id = os.id AND user_id = $userId LIMIT 1) AS is_completed`. This function also has a different id than the current function.

```php
 2 * 1024 * 1024) { // 2MB max file size
            $errorMessage = "File size too large. Maximum allowed size is 2MB.";
        } else {
            if (move_uploaded_file($fileTmp, $profilePath)) {
                $profileImage = $profilePath;
                $successMessage = "Profile image uploaded successfully.";
            } else {
                $errorMessage = "Failed to move the uploaded file.";
            }
        }
    }
}

if (!file_exists($profileImage)) {
    $profileImage = 'uploads/default_profile.png';
}
?>
... 


    
    
     Dashboard
    
    
    




    
     Dashboard







    
        " alt="Profile Picture" 
             class="rounded-circle border border-light shadow-sm"
             style="width: 90px; height: 90px; object-fit: cover;">
        
        
    

    
        
        
            " href="?page=dashboard">
                 Dashboard
            
        
        
        
        
            " href="?page=profile">
                 Profile
            
        

        
        
            " href="?page=payment">
                 Pay Membership
            
        

        
        prepare($paymentCheckQuery);
$paymentCheckStmt->bind_param("i", $userId);
$paymentCheckStmt->execute();
$paymentCheckResult = $paymentCheckStmt->get_result();
$hasPaid = $paymentCheckResult->num_rows > 0;
?>


     " 
       href="" 
        onclick="">
         Assign Jeepney
    



.disabled-link {
    opacity: 0.6;
    pointer-events: none;
    cursor: not-allowed;
}


        
        
            
                 Logout
            
        
    





    
        
        
    
        
            " alt="Profile Picture" class="rounded-circle shadow" width="110" height="110">
        
        
        
    


        


    
        
            🕒 Today's Schedule
        
        
        = ? AND os.target_role = 'driver'
            ORDER BY os.orientation_date ASC, os.orientation_time ASC
            LIMIT 1
        ";

        $stmt = $conn->prepare($todayQuery);
        $stmt->bind_param("is", $userId, $today);
        $stmt->execute();
        $todayResult = $stmt->get_result();

        if ($todayResult->num_rows > 0):
            $sched = $todayResult->fetch_assoc();
            $hasAttended = !empty($sched['attendee_user_id']);
            $mode = strtolower($sched['attended_mode'] ?? '');
            $isToday = ($sched['orientation_date'] === $today);
        ?>

        Title: 
        Date: 
            
                Upcoming
            
        
        Time: 

        Mode:
            ">
            ">
                
            
        

        
        
        
        function startCountdown(datetime) {
            const target = new Date(datetime).getTime();
            const timer = setInterval(() => {
                const now = new Date().getTime();
                const diff = target - now;
                if (diff ");
        

        
        
            
                You haven't confirmed your attendance yet.
                , 'online')" class="btn btn-outline-primary btn-sm">Attend Online
                , 'in-person')" class="btn btn-outline-secondary btn-sm">Attend In-Person
            
        
            
                 Orientation Completed 
                → Pay Membership Fee Now
            
        
            
                 Waiting for manager to mark as completed...
            
        

        
            No schedules available. Stay tuned!
        
        
    




            prepare($requestQuery);
$requestStmt->bind_param("i", $userId); // Make sure $userId is set from session
$requestStmt->execute();
$requestResult = $requestStmt->get_result();
$hasRequestedOrientation = $requestResult->num_rows > 0;
?>




    
        
            📅 Upcoming Orientation
        
        
        = CURDATE() AND os.target_role = 'driver'
    ORDER BY os.orientation_date ASC
    LIMIT 1";

$scheduleResult = $conn->query($scheduleQuery);
$scheduleAvailable = ($scheduleResult && $scheduleResult->num_rows > 0);
?>

    fetch_assoc(); ?>
    Title: 
    Date: 
    Time: 

    
        
            
            This orientation has already been completed.
        
    
        
            
                📡 View Online
            
            
                🏢 View In-Person
            
        

        
            
                Meeting Link: " target="_blank">Join Meeting
                , 'online')">✅ Attend Online
            
        

        
            
                Venue: 
                , 'in-person')">✅ Attend In-Person
            
        
    

    
    
        Click the button below to notify the manager that you're ready to attend orientation.
        
    I'm Ready to Attend Orientation!



    
        ✅ You’ve already requested orientation.
        ⏳ Please wait for the manager to post the schedule.
    



        
    

    
        
        
            
                👤 My Profile
            
            
                " class="rounded-circle mb-3" width="120" alt="Profile Picture">
                
                    
                        
                    
                    
                        
                    
                    
                    Upload Photo
                
                
                
                    First Name:
                    Last Name:
                    Email:
                
            
        
    
        
        
            
                💳 Membership Payment
            
            
                This feature is under development.
                Please visit again later for payment options.
            
        

    
        
        
            
                 Assign Jeepney
            
            
                Jeepney Assignment Module
                Select Jeepney:
            
        
    










function showOrientation(mode) {
    document.getElementById('onlineDetails').classList.add('d-none');
    document.getElementById('inpersonDetails').classList.add('d-none');
    if (mode === 'online') {
        document.getElementById('onlineDetails').classList.remove('d-none');
    } else if (mode === 'inperson') {
        document.getElementById('inpersonDetails').classList.remove('d-none');
    }
}

async function requestOrientation() {
  const result = await Swal.fire({
    title: 'Confirm Request?',
    text: 'Are you sure you want to notify the manager?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, Request!',
    cancelButtonText: 'No, Cancel',
    confirmButtonColor: '#198754',
  });

  if (result.isConfirmed) {
    // AJAX request to the server
    $.ajax({
      url: 'request_orientation.php',
      type: 'POST',
      data: { user_id:  }, // User ID from PHP session
      success: function(response) {
        if (response === 'success') {
          Swal.fire({
            icon: 'success',
            title: 'Request Sent!',
            text: 'We will notify you when the schedule is released.',
          }).then(() => {
            location.reload();
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Request Failed',
            text: 'Please try again later.',
          });
        }
      },
      error: function() {
        Swal.fire({
          icon: 'error',
          title: 'Oops...',
          text: 'Something went wrong!',
        });
      }
    });
  }
}

async function submitAttendance(orientationId, attendedMode) {
    // Show a confirmation dialog
    const result = await Swal.fire({
        title: 'Confirm Attendance?',
        text: `Are you sure you want to confirm your attendance ${attendedMode === 'online' ? 'online' : 'in-person'}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, Confirm!',
        cancelButtonText: 'No, Cancel',
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33'
    });

    if (result.isConfirmed) {
        // AJAX request to submit attendance
        $.ajax({
            url: 'submit_attendance.php',
            type: 'POST',
            data: { orientation_id: orientationId, user_id: , attended_mode: attendedMode },
            success: function(response) {
                if (response === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Attendance Submitted!',
                        text: 'Your attendance has been recorded successfully.',
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Submission Failed',
                        text: 'Please try again later.',
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Something went wrong!',
                });
            }
        });
    }
}




Citations:
[1] https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/attachments/65882479/aa293dbe-1349-4ec8-9630-43cc2e54ef59/paste-2.txt
[2] https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/attachments/65882479/88293f2c-a033-423c-b0bd-4593a2150374/paste-2.txt
[3] https://ppl-ai-file-upload.s3.amazonaws.com/web/direct-files/attachments/65882479/8b6ed87c-e0ef-416c-ba59-56752228d2e4/paste-3.txt

---
Answer from Perplexity: pplx.ai/share