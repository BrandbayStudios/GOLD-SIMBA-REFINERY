{{-- ========================= VENDOR PROFILE MODAL ========================= --}}
<div class="modal profile-modal" id="profileModal">
    <div class="modal-box">
        <div class="profile-cover">
            <img id="profileCoverImage" src="" alt="">
            <button class="profile-close" onclick="closeModal('profileModal')"><i class="ri-close-line"></i></button>

            <div class="profile-cover-content">
                <div class="profile-identity">
                    <div class="profile-logo"><img id="profileLogo" src="" alt=""></div>
                    <div>
                        <div class="profile-name" id="profileName">Vendor Name</div>
                        <div class="profile-verified"><i class="ri-verified-badge-fill"></i> Verified Vendor</div>
                        <div class="profile-location" id="profileLocation">Lusaka, Zambia</div>
                    </div>
                </div>

                <div class="profile-top-actions">
                    <button class="profile-whatsapp" id="profileWhatsApp"><i class="ri-whatsapp-line"></i> WhatsApp</button>
                    <button class="profile-quote" onclick="openQuoteFromProfile()">Request Quote</button>
                </div>
            </div>
        </div>

        <div class="profile-body">
            <div class="profile-layout">
                <main class="profile-main">
                    <section class="profile-section">
                        <h3>About</h3>
                        <p id="profileAbout"></p>
                        <div class="profile-facts">
                            <div class="fact"><strong id="profileExperience">8+</strong><span>Years Experience</span></div>
                            <div class="fact"><strong id="profileEvents">250+</strong><span>Events</span></div>
                            <div class="fact"><strong id="profileRating">4.9</strong><span>Average Rating</span></div>
                        </div>
                    </section>

                    <section class="profile-section">
                        <h3>Services</h3>
                        <div class="service-tags" id="profileServices"></div>
                    </section>

                    <section class="profile-section">
                        <h3>Packages</h3>
                        <div class="packages" id="profilePackages"></div>
                    </section>

                    <section class="profile-section">
                        <h3>Portfolio</h3>
                        <div class="portfolio-grid" id="profilePortfolio"></div>
                    </section>

                    <section class="profile-section">
                        <h3>Reviews</h3>
                        <div class="review-summary">
                            <div class="average-rating">
                                <strong id="reviewAverage">4.9</strong>
                                <div class="stars">★★★★★</div>
                                <small id="reviewCount">38 reviews</small>
                            </div>
                            <div class="review-bars">
                                <div class="review-bar">5<div class="bar"><span style="width:90%"></span></div>90%</div>
                                <div class="review-bar">4<div class="bar"><span style="width:7%"></span></div>7%</div>
                                <div class="review-bar">3<div class="bar"><span style="width:2%"></span></div>2%</div>
                                <div class="review-bar">2<div class="bar"><span style="width:1%"></span></div>1%</div>
                                <div class="review-bar">1<div class="bar"><span style="width:0%"></span></div>0%</div>
                            </div>
                        </div>
                        <div id="profileReviews"></div>

                        {{-- WRITE A REVIEW --}}
                        <div class="review-form" id="reviewForm">
                            <h4>Write a review</h4>
                            <div class="review-stars-picker" id="reviewStars">
                                @for ($i = 1; $i <= 5; $i++)
                                    <button type="button" class="review-star" data-value="{{ $i }}" onclick="setReviewRating({{ $i }})"><i class="ri-star-line"></i></button>
                                @endfor
                            </div>
                            <textarea id="reviewBody" placeholder="Share your experience with this vendor..."></textarea>
                            <button class="submit-btn" type="button" onclick="submitReview()">Post Review</button>
                        </div>
                    </section>
                </main>

                <aside class="profile-sidebar">
                    <div class="availability-card">
                        <div class="availability-status">
                            <span class="status-dot"></span>
                            <span id="availabilityText">Currently accepting enquiries</span>
                        </div>
                        <p>Contact this vendor to check availability for your event date.</p>
                    </div>

                    <section class="profile-section">
                        <h3>Connect</h3>
                        <p>Ready to discuss your event? Send the vendor your requirements.</p>
                        <button class="sidebar-contact sidebar-whatsapp" id="sidebarWhatsApp"><i class="ri-whatsapp-line"></i> Contact on WhatsApp</button>
                        <button class="sidebar-contact sidebar-quote" onclick="openQuoteFromProfile()"><i class="ri-file-list-3-line"></i> Request a Quote</button>
                    </section>

                    <section class="profile-section">
                        <h3>Event Types</h3>
                        <div class="service-tags" id="profileEventTypes"></div>
                    </section>
                </aside>
            </div>
        </div>
    </div>
</div>

{{-- ========================= QUOTE MODAL ========================= --}}
<div class="modal" id="quoteModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Request a Quote</h3>
            <button class="modal-close" onclick="closeModal('quoteModal')"><i class="ri-close-line"></i></button>
        </div>
        <div class="modal-content">
            <div style="margin-bottom:20px;color:#777;font-size:13px;">
                Requesting a quote from <strong id="quoteVendorName"></strong>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" id="quoteName" placeholder="Full name">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="quotePhone" placeholder="+260">
                </div>
                <div class="form-group">
                    <label>Event Type</label>
                    <select id="quoteEvent">
                        <option>Wedding</option>
                        <option>Birthday</option>
                        <option>Corporate Event</option>
                        <option>Graduation</option>
                        <option>Traditional Ceremony</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Event Date</label>
                    <input type="date" id="quoteDate">
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" id="quoteLocation" placeholder="Event location">
                </div>
                <div class="form-group">
                    <label>Guest Count</label>
                    <input type="number" id="quoteGuests" placeholder="e.g. 150">
                </div>
                <div class="form-group full">
                    <label>Requirements</label>
                    <textarea id="quoteRequirements" placeholder="Tell the vendor what you need..."></textarea>
                </div>
            </div>
            <button class="submit-btn" onclick="submitQuote()">Send Quote Request</button>
        </div>
    </div>
</div>

{{-- ========================= PLAN EVENT MODAL ========================= --}}
<div class="modal" id="plannerModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Plan My Event</h3>
            <button class="modal-close" onclick="closeModal('plannerModal')"><i class="ri-close-line"></i></button>
        </div>
        <div class="modal-content">
            <div class="form-grid">
                <div class="form-group">
                    <label>Your Name</label>
                    <input type="text" id="plannerName" placeholder="Full name">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="plannerPhone" placeholder="+260">
                </div>
                <div class="form-group">
                    <label>Event Type</label>
                    <select id="plannerEventType">
                        <option>Wedding</option>
                        <option>Birthday</option>
                        <option>Corporate Event</option>
                        <option>Graduation</option>
                        <option>Traditional Ceremony</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Event Date</label>
                    <input type="date" id="plannerDate">
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" id="plannerLocation" placeholder="City / location">
                </div>
                <div class="form-group">
                    <label>Guest Count</label>
                    <input type="number" id="plannerGuests" placeholder="Estimated guests">
                </div>
                <div class="form-group full">
                    <label>What do you need?</label>
                    <textarea id="plannerNeeds" placeholder="Tell us about your event and the services you need..."></textarea>
                </div>
            </div>
            <button class="submit-btn" onclick="submitPlanner()">Start Planning</button>
        </div>
    </div>
</div>

{{-- ========================= VENDOR MODAL ========================= --}}
<div class="modal" id="vendorModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Become a Vendor</h3>
            <button class="modal-close" onclick="closeModal('vendorModal')"><i class="ri-close-line"></i></button>
        </div>
        <div class="modal-content">
            <p style="color:#777;font-size:13px;margin-bottom:22px;">
                List your business on EventHub Zambia and connect with customers planning events.
            </p>
            <div class="form-grid">
                <div class="form-group">
                    <label>Business Name</label>
                    <input type="text" id="vendorBusinessName" placeholder="Business name">
                </div>
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" id="vendorContactPerson" placeholder="Full name">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" id="vendorPhone" placeholder="+260">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="vendorEmail" placeholder="Business email">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select id="vendorCategory">
                        <option value="Venue">Venue</option>
                        <option value="Decor">Decor</option>
                        <option value="Photography">Photography</option>
                        <option value="Catering">Catering</option>
                        <option value="DJ">DJ & Entertainment</option>
                        <option value="Beauty">Beauty</option>
                        <option value="Car Hire">Car Hire</option>
                        <option value="Fashion & Outfits">Fashion & Outfits</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <select id="vendorLocation">
                        <option>Lusaka</option>
                        <option>Ndola</option>
                        <option>Kitwe</option>
                        <option>Livingstone</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Facebook Page / Profile URL <span style="font-weight:500;text-transform:none;letter-spacing:0;color:#999;">(optional)</span></label>
                    <input type="url" id="vendorFacebookUrl" placeholder="https://facebook.com/yourbusiness">
                </div>
                <div class="form-group full">
                    <label>Tell us about your business</label>
                    <textarea id="vendorDescription" placeholder="Describe your services..."></textarea>
                </div>
            </div>
            <button class="submit-btn" onclick="saveVendorApplication()">Submit Vendor Application</button>
        </div>
    </div>
</div>

{{-- ========================= AUTHENTICATION MODAL ========================= --}}
<div class="modal" id="authModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="authTitle">Welcome back</h3>
            <button class="modal-close" onclick="closeModal('authModal')"><i class="ri-close-line"></i></button>
        </div>
        <div class="modal-content">
            <form id="authForm" onsubmit="submitAuthForm(event)">
                <div class="form-grid">
                    <div class="form-group full" id="authNameGroup">
                        <label>Full Name</label>
                        <input type="text" id="authName" placeholder="Your full name">
                    </div>
                    <div class="form-group full" id="authPhoneGroup">
                        <label>Phone Number</label>
                        <input type="tel" id="authPhone" placeholder="+260">
                    </div>
                    <div class="form-group full">
                        <label>Email</label>
                        <input type="email" id="authEmail" placeholder="you@example.com" required>
                    </div>
                    <div class="form-group full">
                        <label>Password</label>
                        <input type="password" id="authPassword" placeholder="Minimum 6 characters" minlength="6" required>
                    </div>
                    <div class="form-group full" id="authConfirmGroup">
                        <label>Confirm Password</label>
                        <input type="password" id="authPasswordConfirm" placeholder="Re-enter password" minlength="6">
                    </div>
                </div>
                <button class="submit-btn" id="authSubmit" type="submit">Login</button>
            </form>
            <div id="authSwitch" style="text-align:center;margin-top:16px;color:#777;font-size:12px;"></div>
        </div>
    </div>
</div>
