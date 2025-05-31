<?php
/**
 * Profile Testimonials Component
 * Displays approved testimonials on user profiles
 * 
 * Usage: include this file in profile pages and pass $profileUserId
 */

if (!isset($profileUserId)) {
    echo '<div class="alert alert-danger">Error: Profile user ID not provided</div>';
    return;
}

require_once 'TestimonialManager.php';

$testimonialManager = new TestimonialManager($pdo);
$result = $testimonialManager->getApprovedTestimonialsForProfile($profileUserId, 10);

if (!$result['success']) {
    echo '<div class="alert alert-danger">Error loading testimonials: ' . htmlspecialchars($result['error']) . '</div>';
    return;
}

$testimonials = $result['testimonials'];
$isOwnProfile = isset($_SESSION['user']) && $_SESSION['user']['id'] == $profileUserId;
?>

<div class="testimonials-section">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>
            <i class="fas fa-star text-warning"></i> 
            Testimonials 
            <?php if (count($testimonials) > 0): ?>
                <span class="badge bg-primary"><?php echo count($testimonials); ?></span>
            <?php endif; ?>
        </h4>
        
        <?php if (!$isOwnProfile && isset($_SESSION['user'])): ?>
            <button class="btn btn-outline-primary btn-sm" onclick="openWriteTestimonialModal(<?php echo $profileUserId; ?>)">
                <i class="fas fa-plus"></i> Write Testimonial
            </button>
        <?php endif; ?>
    </div>
    
    <?php if (count($testimonials) > 0): ?>
        <div class="testimonials-list">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="testimonial-card card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <img src="<?php echo $testimonial['writer_profile_pic'] ?: 'assets/images/default-avatar.png'; ?>" 
                                 alt="Profile" class="rounded-circle me-3" width="50" height="50">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-0">
                                            <a href="profile.php?id=<?php echo $testimonial['writer_id']; ?>" class="text-decoration-none">
                                                <strong><?php echo htmlspecialchars($testimonial['writer_first_name'] . ' ' . $testimonial['writer_last_name']); ?></strong>
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar-alt"></i> 
                                            <?php echo date('M j, Y', strtotime($testimonial['approved_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="testimonial-rating">
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                        <i class="fas fa-star text-warning"></i>
                                    </div>
                                </div>
                                <p class="testimonial-content mb-0">
                                    "<?php echo nl2br(htmlspecialchars($testimonial['content'])); ?>"
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($testimonials) >= 10): ?>
            <div class="text-center mt-3">
                <button class="btn btn-outline-secondary" onclick="loadMoreTestimonials(<?php echo $profileUserId; ?>)">
                    <i class="fas fa-chevron-down"></i> Load More Testimonials
                </button>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="text-center py-4">
            <i class="fas fa-star fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">No testimonials yet</h5>
            <?php if ($isOwnProfile): ?>
                <p class="text-muted">Testimonials from others will appear here once they're approved.</p>
                <a href="testimonials.php" class="btn btn-outline-primary">
                    <i class="fas fa-cog"></i> Manage Testimonials
                </a>
            <?php else: ?>
                <p class="text-muted">Be the first to write a testimonial for this person!</p>
                <button class="btn btn-primary" onclick="openWriteTestimonialModal(<?php echo $profileUserId; ?>)">
                    <i class="fas fa-plus"></i> Write First Testimonial
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Write Testimonial Modal -->
<div class="modal fade" id="writeTestimonialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-star text-warning"></i> 
                    Write a Testimonial
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickTestimonialForm">
                    <input type="hidden" id="quickRecipientId" name="recipient_user_id">
                    <div class="mb-3">
                        <label for="quickTestimonialContent" class="form-label">Your Testimonial</label>
                        <textarea class="form-control" id="quickTestimonialContent" name="content" rows="5" 
                                  placeholder="Write a thoughtful testimonial about this person's skills, character, or work ethic..." 
                                  required minlength="10" maxlength="1000"></textarea>
                        <div class="form-text">
                            <span id="quickCharCount">0</span>/1000 characters (minimum 10)
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Your testimonial will be sent to the recipient for approval before it appears on their profile.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitQuickTestimonial()">
                    <i class="fas fa-paper-plane"></i> Submit Testimonial
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.testimonial-card {
    border-left: 4px solid #ffc107;
    transition: transform 0.2s ease-in-out;
}

.testimonial-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.testimonial-content {
    font-style: italic;
    line-height: 1.6;
}

.testimonial-rating {
    opacity: 0.8;
}
</style>

<script>
function openWriteTestimonialModal(userId) {
    document.getElementById('quickRecipientId').value = userId;
    document.getElementById('quickTestimonialContent').value = '';
    document.getElementById('quickCharCount').textContent = '0';
    
    const modal = new bootstrap.Modal(document.getElementById('writeTestimonialModal'));
    modal.show();
}

// Character counter for quick testimonial
document.addEventListener('DOMContentLoaded', function() {
    const quickContent = document.getElementById('quickTestimonialContent');
    const quickCharCount = document.getElementById('quickCharCount');
    
    if (quickContent && quickCharCount) {
        quickContent.addEventListener('input', function() {
            quickCharCount.textContent = this.value.length;
        });
    }
});

function submitQuickTestimonial() {
    const form = document.getElementById('quickTestimonialForm');
    const formData = new FormData(form);
    
    const data = {
        recipient_user_id: formData.get('recipient_user_id'),
        content: formData.get('content')
    };
    
    fetch('api/write_testimonial.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('writeTestimonialModal'));
            modal.hide();
            
            // Show success message
            showAlert('success', data.message);
            
            // Reset form
            form.reset();
            document.getElementById('quickCharCount').textContent = '0';
        } else {
            showAlert('danger', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while submitting the testimonial.');
    });
}

function loadMoreTestimonials(userId) {
    // Implementation for loading more testimonials
    // This would involve AJAX call to get more testimonials with offset
    console.log('Loading more testimonials for user:', userId);
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at the top of the testimonials section
    const testimonialsSection = document.querySelector('.testimonials-section');
    testimonialsSection.insertBefore(alertDiv, testimonialsSection.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>