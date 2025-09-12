# Approval/Rejection Modal Documentation

## Overview
The CRMFMS Leave Management System now includes a modal interface for approving and rejecting leave requests, allowing approvers to provide reasons for their decisions.

## Features

### **Modal-Based Approval Process**
- **Confirmation Modal**: Prevents accidental approvals/rejections
- **Reason Entry**: Optional approval reasons, recommended rejection reasons
- **Visual Feedback**: Different styling for approval vs rejection actions
- **Reason Display**: Shows approval/rejection reasons in the leave requests table

### **User Experience Improvements**
- **Clear Intent**: Modal clearly shows the action being taken
- **Reason Tracking**: All approval/rejection decisions can include explanatory text
- **Better Communication**: Faculty can see why their requests were approved or rejected
- **Audit Trail**: Complete history of decisions with reasons

## Implementation Details

### **Database Changes**
```sql
ALTER TABLE leave_requests 
ADD COLUMN approval_reason TEXT NULL AFTER reviewed_at,
ADD COLUMN rejection_reason TEXT NULL AFTER approval_reason;
```

### **Backend API Changes** (`/api/leaves/leaves.php`)

#### **Enhanced Review Action**
```php
if ($action === 'review') {
    $status = $input['status'];
    $leave_id = $input['leave_id'];
    $reason = $input['reason'] ?? '';
    
    if ($status === 'approved') {
        $stmt = $db->prepare("UPDATE leave_requests 
                              SET status=:st, reviewed_by=:rb, reviewed_at=NOW(), 
                                  approval_reason=:reason, rejection_reason=NULL
                              WHERE leave_id=:id");
    } else if ($status === 'denied') {
        $stmt = $db->prepare("UPDATE leave_requests 
                              SET status=:st, reviewed_by=:rb, reviewed_at=NOW(), 
                                  rejection_reason=:reason, approval_reason=NULL
                              WHERE leave_id=:id");
    }
}
```

#### **Enhanced Data Retrieval**
```php
$stmt = $db->query("SELECT l.*, CONCAT(u.first_name,' ',u.last_name) AS user_name,
                           (SELECT CONCAT(first_name,' ',last_name) FROM users WHERE user_id=l.reviewed_by) AS reviewer,
                           l.approval_reason, l.rejection_reason
                    FROM leave_requests l
                    JOIN users u ON u.user_id=l.user_id
                    ORDER BY l.requested_at DESC");
```

### **Frontend Changes**

#### **Modal HTML** (`/public/modules/leaves/leaves.html`)
```html
<!-- Approval/Rejection Modal -->
<div class="modal fade" id="reviewLeaveModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reviewLeaveModalLabel">Review Leave Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="reviewLeaveForm">
          <input type="hidden" id="reviewLeaveId" name="leave_id">
          <input type="hidden" id="reviewAction" name="action">
          
          <div class="mb-3">
            <label for="reviewReason" class="form-label">
              <span id="reasonLabel">Reason for Decision</span>
              <small class="text-muted">(Optional but recommended)</small>
            </label>
            <textarea class="form-control" id="reviewReason" name="reason" rows="4" 
                      placeholder="Provide a reason for your decision..."></textarea>
          </div>
          
          <div class="alert alert-info" role="alert">
            <strong>Decision:</strong> <span id="decisionText"></span>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn" id="confirmReviewBtn">
          <span id="confirmBtnText">Confirm</span>
        </button>
      </div>
    </div>
  </div>
</div>
```

#### **JavaScript Functionality** (`/public/modules/leaves/leaves.js`)

##### **Modal Opening Function**
```javascript
function openReviewModal(leaveId, action) {
  reviewLeaveId.value = leaveId;
  reviewAction.value = 'review';
  reviewReason.value = '';
  
  if (action === 'approved') {
    document.getElementById('reviewLeaveModalLabel').textContent = 'Approve Leave Request';
    reasonLabel.textContent = 'Approval Reason';
    decisionText.textContent = 'You are about to APPROVE this leave request.';
    confirmReviewBtn.className = 'btn btn-success';
    confirmBtnText.textContent = 'Approve';
    reviewReason.placeholder = 'Provide a reason for approval (optional)...';
  } else if (action === 'denied') {
    document.getElementById('reviewLeaveModalLabel').textContent = 'Reject Leave Request';
    reasonLabel.textContent = 'Rejection Reason';
    decisionText.textContent = 'You are about to REJECT this leave request.';
    confirmReviewBtn.className = 'btn btn-danger';
    confirmBtnText.textContent = 'Reject';
    reviewReason.placeholder = 'Please provide a reason for rejection (recommended)...';
  }
  
  confirmReviewBtn.dataset.action = action;
  reviewModal.show();
}
```

##### **Enhanced Table Display**
```javascript
// Build reason display with approval/rejection reasons if available
let reasonDisplay = l.reason || '-';
if (l.status === 'approved' && l.approval_reason) {
  reasonDisplay += `<br><small class="text-success"><strong>Approval Reason:</strong> ${l.approval_reason}</small>`;
} else if (l.status === 'denied' && l.rejection_reason) {
  reasonDisplay += `<br><small class="text-danger"><strong>Rejection Reason:</strong> ${l.rejection_reason}</small>`;
}
```

## Usage Flow

### **For Approvers (Dean, Secretary, Program Head)**

1. **View Leave Requests**: Navigate to the leave management page
2. **Click Action Button**: Click "Approve" or "Deny" for pending requests
3. **Modal Opens**: Confirmation modal appears with appropriate styling
4. **Enter Reason**: Optionally provide a reason for the decision
5. **Confirm Action**: Click the colored confirmation button
6. **Request Updated**: Leave request status changes and reason is saved

### **For Faculty Members**

1. **View Requests**: Check the leave requests table
2. **See Decisions**: View approval/rejection status
3. **Read Reasons**: See approval or rejection reasons if provided
4. **Understand Feedback**: Get clear communication about decisions

## Visual Design

### **Approval Modal**
- **Color**: Green (success theme)
- **Button**: Green "Approve" button
- **Message**: "You are about to APPROVE this leave request."
- **Reason Label**: "Approval Reason"
- **Placeholder**: "Provide a reason for approval (optional)..."

### **Rejection Modal**
- **Color**: Red (danger theme)
- **Button**: Red "Reject" button
- **Message**: "You are about to REJECT this leave request."
- **Reason Label**: "Rejection Reason"
- **Placeholder**: "Please provide a reason for rejection (recommended)..."

### **Table Display**
- **Approval Reasons**: Green text with "Approval Reason:" label
- **Rejection Reasons**: Red text with "Rejection Reason:" label
- **Original Request Reason**: Displayed above the decision reason

## Benefits

### **For Approvers**
1. **Prevent Mistakes**: Modal confirmation prevents accidental clicks
2. **Clear Communication**: Easy way to explain decisions
3. **Professional Process**: Structured approval workflow
4. **Audit Trail**: Complete record of all decisions and reasons

### **For Faculty**
1. **Transparency**: Clear understanding of why requests were approved/rejected
2. **Feedback Loop**: Opportunity to improve future requests
3. **Communication**: Better relationship with administrators
4. **Documentation**: Record of all decisions for reference

### **For Administration**
1. **Compliance**: Proper documentation of all leave decisions
2. **Consistency**: Standardized approval process
3. **Accountability**: Clear record of who made decisions and why
4. **Reporting**: Better data for leave policy analysis

## Technical Benefits

### **Data Integrity**
- **Clear Separation**: Approval and rejection reasons stored separately
- **Null Handling**: Proper handling of optional reason fields
- **Data Validation**: Backend validates reason input

### **User Experience**
- **Responsive Design**: Modal works on all screen sizes
- **Keyboard Navigation**: Proper tab order and accessibility
- **Error Handling**: Clear error messages and validation

### **Maintainability**
- **Modular Code**: Separate functions for different actions
- **Consistent Styling**: Bootstrap classes for consistent appearance
- **Easy Updates**: Simple to modify modal content or styling

## Future Enhancements

### **Potential Improvements**
1. **Template Reasons**: Pre-defined common reasons for quick selection
2. **Email Notifications**: Automatic emails to faculty with decisions
3. **Approval Chains**: Multiple approvers for different leave types
4. **Time Tracking**: Track how long requests take to process
5. **Analytics**: Reports on approval/rejection patterns

### **Advanced Features**
1. **Conditional Logic**: Different reasons required based on leave type
2. **Attachment Support**: Allow approvers to attach documents
3. **Comments History**: Multiple comments per request
4. **Escalation**: Automatic escalation for delayed approvals
5. **Integration**: Connect with HR systems or external tools

## Testing Scenarios

### **Approval Testing**
1. ✅ Approve with reason provided
2. ✅ Approve without reason (should work)
3. ✅ Verify approval reason displays in table
4. ✅ Verify status changes to "approved"

### **Rejection Testing**
1. ✅ Reject with reason provided
2. ✅ Reject without reason (should work)
3. ✅ Verify rejection reason displays in table
4. ✅ Verify status changes to "denied"

### **UI Testing**
1. ✅ Modal opens correctly for approval
2. ✅ Modal opens correctly for rejection
3. ✅ Correct colors and text for each action
4. ✅ Cancel button works properly
5. ✅ Form validation works correctly

## Support

For technical support or questions about the approval modal functionality, refer to the system administrator or check the application logs for detailed error information.

