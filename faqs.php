<?php
require_once __DIR__ . '/./lbpanel/config/session.php';
wdx_session_start();
include "lbpanel/config/connection.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQs • WoodEx Furniture Pakistan</title>
    <meta name="description" content="Common questions about delivery, customization, materials, warranty, and services from WoodEx Furniture Pakistan.">
    <link rel="icon" type="image/x-icon" href="<?php echo $domain;?>assets/images/favicon.ico">

    <!-- Fonts & CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo $domain;?>assets/style.css" rel="stylesheet">

    <style>
        /* FAQ Hero */
        .faq-hero {
            background-color: var(--light-color);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 70px 0 40px;
        }
        .faq-hero h1 {
            font-family: var(--fancy);
            font-weight: 700;
            color: var(--primary-color);
        }
        .faq-hero p {
            color: var(--secondary-color);
            max-width: 700px;
            font-size: 1rem;
        }
        .badge-soft {
            background: var(--accent-color);
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.8rem;
            padding: .4rem .9rem;
            border-radius: 50px;
            text-transform: uppercase;
        }

        /* Search + Categories */
        .faq-section {
            padding: 50px 0 80px;
        }
        .faq-search {
            background: #fff;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        .faq-search input {
            border: none;
            outline: none;
            flex: 1;
            font-size: 1rem;
            color: var(--primary-color);
            background: transparent;
        }
        .faq-search i {
            color: var(--secondary-color);
        }

        .faq-note {
            color: var(--secondary-color);
            font-size: 0.9rem;
        }

        .faq-cats {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .faq-chip {
            background: #fff;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 999px;
            padding: 6px 14px;
            color: var(--secondary-color);
            cursor: pointer;
            transition: all 0.25s ease;
            font-size: 0.9rem;
        }
        .faq-chip.active, .faq-chip:hover {
            background: #ffffff;
            color: var(--primary-color);
            border-color: var(--accent-color);
        }

        /* FAQ Items */
        .faq-wrap {
            margin-top: 40px;
        }
        .faq-item {
            background: #fff;
            border: 1px solid rgba(0,0,0,0.1);
            border-radius: 12px;
            margin-bottom: 14px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
        }
        .faq-question {
            cursor: pointer;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 600;
            color: var(--primary-color);
        }
        .faq-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--light-color);
            color: var(--primary-color);
            border: 1px solid rgba(0,0,0,0.05);
            display: grid;
            place-items: center;
            transition: all 0.2s;
        }
        .faq-answer {
            display: none;
            padding: 0 22px 18px;
            color: var(--secondary-color);
            line-height: 1.7;
            text-align: start;
        }
        .faq-item.active .faq-answer {
            display: block;
            text-align: start;
        }
        .faq-item.active .faq-icon {
            background: #ffffff;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
<?php include_once('header/header.php'); ?>

<!-- Hero -->
<section class="faq-hero">
    <div class="container">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <div>
                <h1 class="mb-2">Frequently Asked Questions</h1>
                <p class="mb-0">Find helpful answers about delivery, customization, warranty, and design with <strong>WoodEx Furniture Pakistan</strong>.</p>
            </div>
            <div class="text-nowrap align-self-center">
                <span class="badge-soft">Updated: November 2025</span>
            </div>
        </div>

        <div class="row mt-4 g-3">
            <div class="col-lg-6">
                <div class="faq-search">
                    <i class="bi bi-search"></i>
                    <input id="faqSearch" type="text" placeholder="Search questions (e.g., delivery, custom design, warranty)…">
                </div>
                <div class="faq-note mt-2">Tip: try keywords like <em>material</em>, <em>custom</em>, or <em>installation</em>.</div>
            </div>
            <div class="col-lg-6">
                <div class="faq-cats">
                    <button class="faq-chip active" data-cat="all"><i class="bi bi-grid"></i> All</button>
                    <button class="faq-chip" data-cat="orders"><i class="bi bi-cart-check"></i> Orders</button>
                    <button class="faq-chip" data-cat="delivery"><i class="bi bi-truck"></i> Delivery</button>
                    <button class="faq-chip" data-cat="warranty"><i class="bi bi-shield-check"></i> Warranty</button>
                    <button class="faq-chip" data-cat="design"><i class="bi bi-rulers"></i> Custom Design</button>
                    <button class="faq-chip" data-cat="payments"><i class="bi bi-credit-card"></i> Payments</button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="faq-section">
    <div class="container">
        <div class="faq-wrap" id="faqList">
            <!-- Example FAQs -->
            <div class="faq-item" data-cat="design">
                <div class="faq-question" role="button" aria-expanded="false">
                    <span>Can I order customized office furniture?</span>
                    <span class="faq-icon">+</span>
                </div>
                <div class="faq-answer">
                    Absolutely! We specialize in custom furniture built to your exact dimensions, materials, and finish preferences.
                </div>
            </div>

            <div class="faq-item" data-cat="delivery">
                <div class="faq-question" role="button" aria-expanded="false">
                    <span>How long does delivery take?</span>
                    <span class="faq-icon">+</span>
                </div>
                <div class="faq-answer">
                    Standard delivery within Pakistan usually takes 2–4 weeks. Custom orders may take longer depending on project size.
                </div>
            </div>

            <div class="faq-item" data-cat="warranty">
                <div class="faq-question" role="button" aria-expanded="false">
                    <span>Do you provide warranty on furniture?</span>
                    <span class="faq-icon">+</span>
                </div>
                <div class="faq-answer">
                    Yes, all WoodEx products include a 1-year warranty against manufacturing defects and structural issues.
                </div>
            </div>

            <div class="faq-item" data-cat="payments">
                <div class="faq-question" role="button" aria-expanded="false">
                    <span>What payment methods do you support?</span>
                    <span class="faq-icon">+</span>
                </div>
                <div class="faq-answer">
                    We accept bank transfers, partial advance payments, and secure online payment methods for convenience.
                </div>
            </div>

            <div class="faq-item" data-cat="delivery">
                <div class="faq-question" role="button" aria-expanded="false">
                    <span>Do you offer assembly or installation?</span>
                    <span class="faq-icon">+</span>
                </div>
                <div class="faq-answer">
                    Yes, WoodEx offers professional delivery and installation services to ensure your furniture is perfectly set up.
                </div>
            </div>

            <div class="faq-item" data-cat="orders">
                <div class="faq-question" role="button" aria-expanded="false">
                    <span>Can I visit your showroom before placing an order?</span>
                    <span class="faq-icon">+</span>
                </div>
                <div class="faq-answer">
                    Of course! You’re welcome to visit any of our showrooms in Pakistan to explore designs, finishes, and materials.
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <div class="faq-note">
                Still need help? Email us at <strong>sales@woodexfurniture.pk</strong> — our team is here to assist you.
            </div>
        </div>
    </div>
</section>

<?php include_once('footer/footer.php'); ?>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?php echo $domain;?>assets/script/cart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const items = document.querySelectorAll('.faq-item');
    const questions = document.querySelectorAll('.faq-item .faq-question');
    const searchInput = document.getElementById('faqSearch');
    const chips = document.querySelectorAll('.faq-chip');

    questions.forEach((q) => {
        q.addEventListener('click', function() {
            const parent = q.closest('.faq-item');
            const isActive = parent.classList.contains('active');
            items.forEach(i => {
                i.classList.remove('active');
                const icon = i.querySelector('.faq-icon');
                if (icon) icon.textContent = '+';
                const btn = i.querySelector('.faq-question');
                if (btn) btn.setAttribute('aria-expanded','false');
            });
            if (!isActive) {
                parent.classList.add('active');
                q.querySelector('.faq-icon').textContent = '-';
                q.setAttribute('aria-expanded','true');
            }
        });
    });

    function filterByCategory(cat) {
        items.forEach(i => {
            if (cat === 'all') i.style.display = '';
            else {
                const cats = (i.getAttribute('data-cat') || '').split(' ');
                i.style.display = cats.includes(cat) ? '' : 'none';
            }
        });
    }

    chips.forEach(c => {
        c.addEventListener('click', () => {
            chips.forEach(x => x.classList.remove('active'));
            c.classList.add('active');
            filterByCategory(c.dataset.cat);
            if (searchInput) searchInput.value = '';
        });
    });

    if (searchInput) {
        searchInput.addEventListener('input', function(){
            const term = this.value.trim().toLowerCase();
            items.forEach(i => {
                const q = i.querySelector('.faq-question span')?.textContent.toLowerCase() || '';
                const a = i.querySelector('.faq-answer')?.textContent.toLowerCase() || '';
                i.style.display = (q.includes(term) || a.includes(term)) ? '' : 'none';
            });
        });
    }
});
</script>
</body>
</html>
