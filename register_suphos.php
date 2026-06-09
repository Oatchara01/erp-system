<?php include("head.php"); ?>
<?php include('dbconnect_sale.php'); ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
	var HttPRequest = false;

	function doCallAjax1(bill_id, bill_name, bill_address, bill_tel, tax_id, pre_name, mode_name, email, customer_typename, payment, credit_thb) {
		HttPRequest = false;
		if (window.XMLHttpRequest) {
			HttPRequest = new XMLHttpRequest();
			if (HttPRequest.overrideMimeType) HttPRequest.overrideMimeType('text/html');
		} else if (window.ActiveXObject) {
			try {
				HttPRequest = new ActiveXObject("Msxml2.XMLHTTP");
			} catch (e) {
				try {
					HttPRequest = new ActiveXObject("Microsoft.XMLHTTP");
				} catch (e) {}
			}
		}
		if (!HttPRequest) {
			alert('Cannot create XMLHTTP instance');
			return false;
		}

		var url = 'data_bill_name1.php';
		var pmeters = "bill_id=" + encodeURIComponent(document.getElementById(bill_id).value);
		HttPRequest.open('POST', url, true);
		HttPRequest.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
		HttPRequest.setRequestHeader("Content-length", pmeters.length);
		HttPRequest.setRequestHeader("Connection", "close");
		HttPRequest.send(pmeters);

		HttPRequest.onreadystatechange = function() {
			if (HttPRequest.readyState === 4) {
				// 200 เท่านั้นถึงจะประมวลผล (กัน error เงียบๆ)
				if (HttPRequest.status !== 200) {
					console.error('XHR error', HttPRequest.status);
					return;
				}

				var myProduct = HttPRequest.responseText || '';
				if (myProduct !== "") {
					var myArr = myProduct.split("|");

					document.getElementById(bill_name).value = myArr[0] || "";
					document.getElementById(bill_address).value = myArr[1] || "";
					document.getElementById(bill_tel).value = myArr[2] || "";
					document.getElementById(tax_id).value = myArr[3] || "";

					var preNameVal = (myArr[10] || "").trim();
					var preNameSelect = document.getElementById(pre_name);
					if (preNameSelect) {
						var exists = false;
						for (var i = 0; i < preNameSelect.options.length; i++) {
							if (preNameSelect.options[i].value === preNameVal) {
								exists = true;
								break;
							}
						}
						if (!exists && preNameVal !== "") {
							var opt = document.createElement('option');
							opt.value = preNameVal;
							opt.innerHTML = preNameVal;
							preNameSelect.appendChild(opt);
						}
						preNameSelect.value = preNameVal;
					}
					document.getElementById(mode_name).value = myArr[20] || "";
					document.getElementById(email).value = myArr[21] || "";
					document.getElementById(customer_typename).value = myArr[22] || "";
					document.getElementById(payment).value = myArr[23] || "";
					document.getElementById(credit_thb).value = myArr[24] || "";

					// เก็บ bill_id ที่ค้นหาได้ไว้ใน hidden
					document.getElementById('h_bill_id').value = document.getElementById(bill_id).value;

					// เก็บค่า credit_ckk ของลูกค้าไว้ใน hidden
					var cusCkk = (myArr[23] || "").trim();
					document.getElementById('h_credit_ckk_value').value = cusCkk;

					// อัปเดตโหมดชำระเงินและการแสดงผลอัตโนมัติ
					if (cusCkk !== '0' && cusCkk !== "") {
						switchPaymentMode('credit');
					} else {
						switchPaymentMode('cash');
					}

					// ประเมินการแสดง/ซ่อนตารางหนี้ตามเงื่อนไขใหม่
					evaluateDebtPanel();
				}
			}
		};
	}

	// ----- เงื่อนไขการแสดงตารางหนี้: payment != '0' และมีวงเงิน > 0 -----
	function shouldShowDebtPanel() {
		var payVal = (document.getElementById('payment').value || '').trim();
		var creditRaw = (document.getElementById('credit_thb').value || '0').replace(/,/g, '');
		var creditNum = parseFloat(creditRaw) || 0;
		return (payVal !== '0' && creditNum > 0);
	}

	function evaluateDebtPanel() {
		if (shouldShowDebtPanel()) {
			var cusId = (document.getElementById('h_bill_id').value || '').trim();
			if (cusId) {
				showDebts(cusId);
			} else {
				// ไม่มีลูกค้า -> ซ่อน
				hideDebts();
			}
		} else {
			hideDebts();
		}
	}

	// โหลด options ช่องทางชำระเงิน
	function loadBankOptions(creditOnly, callback) {
		var xhr = new XMLHttpRequest();
		xhr.open('GET', 'bank_options_awl.php?credit_only=' + (creditOnly ? '1' : '0'), true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4) {
				if (xhr.status === 200) {
					var sel = document.getElementById('payment');
					var prev = sel.value; // เก็บค่าเดิมไว้ (กันเด้ง reset กรณีโหลดใหม่)
					sel.innerHTML = '<option value="">**Please Select Item**</option>' + xhr.responseText;
					// พยายาม restore ค่าเดิม ถ้ายังอยู่ใน options ใหม่
					if ([...sel.options].some(o => o.value === prev)) sel.value = prev;

					// ซิงค์ค่าไปยัง dropdown ฝั่ง cash
					var cashSel = document.getElementById('payment_cash_select');
					if (cashSel) {
						cashSel.innerHTML = sel.innerHTML;
						cashSel.value = sel.value;
					}

					if (typeof callback === 'function') {
						callback();
					}
				} else {
					console.error('โหลดช่องทางชำระเงินไม่สำเร็จ');
				}
			}
		};
		xhr.send();
	}

	// แสดงตารางหนี้คงค้าง
	function showDebts(cusId) {
		var xhr = new XMLHttpRequest();
		xhr.open('GET', 'get_debts.php?cus_id=' + encodeURIComponent(cusId), true);
		xhr.onreadystatechange = function() {
			if (xhr.readyState === 4) {
				if (xhr.status === 200) {
					try {
						var res = JSON.parse(xhr.responseText || '{}');

						// ดึงวงเงิน
						var creditInput = document.getElementById('credit_thb').value || '0';
						var credit = parseFloat(String(creditInput).replace(/,/g, '').trim()) || 0;

						// ยอดหนี้
						var rawOutstanding = res.total_outstanding || 0;
						var outstanding = parseFloat(String(rawOutstanding).replace(/,/g, '').trim()) || 0;

						// วงเงินคงเหลือ
						var remain = credit - outstanding;
						document.getElementById('sum_ca').value = remain;

						// Debug
						console.log('credit =', credit,
							'outstanding =', outstanding,
							'remain =', remain);

						var billName = document.getElementById('bill_name').value || '-';

						if (remain < 0) {
							Swal.fire({
								title: "กรุณาติดต่อบัญชี",
								html: "<div style='font-size:16px; text-align:left;'>" +

									"<p>เพื่อขอเพิ่มวงเงินของคุณ เนื่องจากวงเงินของคุณไม่เพียงพอ</p>" +
									"<hr>" +
									"<p><b> " + billName + "</b></p>" +
									"<p><b>วงเงิน: " + numberFormat(credit) + " บาท</b></p>" +
									"<p><b>ยอดวงเงินคงเหลือ:</b> " +
									"<span style='color:red; font-weight:bold;'>" +
									numberFormat(remain) + " บาท" +
									"</span>" +
									"</p>" +
									"</div>",
								icon: "warning",
								confirmButtonText: "กลับหน้าหลัก",
							}).then(() => {
								window.location.href = "main_suphos_so.php";
							});

							return;
						}

						// Summary
						var summaryHtml = `
            <div style="margin-bottom:16px; font-weight:bold;">
              วงเงิน: ${numberFormat(credit)} |
              ยอดวงเงินคงเหลือ:
              <span style="color:${remain < 0 ? 'red' : 'green'};">
                ${numberFormat(remain)}
              </span>
            </div>
          `;

						// แสดงผล
						document.getElementById('debt_table_wrap').innerHTML =
							summaryHtml + (res.html || '');
						document.getElementById('debt_panel').style.display = 'block';

					} catch (e) {
						console.error('Parse JSON error', e);
						hideDebts();
					}
				} else {
					console.error('โหลดยอดหนี้คงค้างไม่สำเร็จ');
					hideDebts();
				}
			}
		};
		xhr.send();
	}


	function hideDebts() {
		document.getElementById('debt_panel').style.display = 'none';
		document.getElementById('debt_table_wrap').innerHTML = '';
		document.getElementById('credit_summary').innerHTML = '';
	}

	// utility แปลงตัวเลขให้อ่านง่าย
	function numberFormat(n) {
		return (Number(n) || 0).toLocaleString('en-US', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}

	// กรณีผู้ใช้แก้ไขค่า payment เองภายหลัง
	document.addEventListener('DOMContentLoaded', function() {
		// เริ่มต้นโหลดในโหมดเงินสด/เครดิตตามค่าเริ่มต้น
		switchPaymentMode('cash');

		// ผู้ใช้แก้ไขวงเงิน -> ประเมินใหม่ (ถ้าวงเงินเป็น 0 จะซ่อน)
		document.getElementById('credit_thb').addEventListener('input', function() {
			evaluateDebtPanel();
			updateCreditDisplay();
		});
	});

	// สลับโหมดการชำระเงิน (เครดิต / เงินสด)
	function switchPaymentMode(mode) {
		var isCredit = (mode === 'credit');

		var radCredit = document.getElementById('pay_mode_credit');
		var radCash = document.getElementById('pay_mode_cash');

		if (isCredit) {
			if (radCredit) radCredit.checked = true;
			document.getElementById('lbl-pay_mode_credit')?.classList.add('active');
			document.getElementById('lbl-pay_mode_cash')?.classList.remove('active');

			document.getElementById('row-credit-fields').style.display = 'grid';
			document.getElementById('row-cash-fields').style.display = 'none';

			loadBankOptions(false, function() {
				var savedCusCkk = document.getElementById('h_credit_ckk_value').value || '';
				var sel = document.getElementById('payment');
				if (savedCusCkk && savedCusCkk !== '0') {
					sel.value = savedCusCkk;
				} else {
					if (sel.options.length > 1) {
						sel.selectedIndex = 1;
					}
				}
				updateCreditDisplay();
				evaluateDebtPanel();
			});
		} else {
			if (radCash) radCash.checked = true;
			document.getElementById('lbl-pay_mode_cash')?.classList.add('active');
			document.getElementById('lbl-pay_mode_credit')?.classList.remove('active');

			document.getElementById('row-cash-fields').style.display = 'grid';
			document.getElementById('row-credit-fields').style.display = 'none';

			loadBankOptions(true, function() {
				var sel = document.getElementById('payment');
				var savedCusCkk = document.getElementById('h_credit_ckk_value').value || '';
				if (savedCusCkk === '0' || !savedCusCkk) {
					if (sel.options.length > 1) {
						sel.selectedIndex = 1;
					}
				} else {
					sel.value = '';
				}
				var cashSel = document.getElementById('payment_cash_select');
				if (cashSel) cashSel.value = sel.value;
				evaluateDebtPanel();
			});
		}
	}

	// อัปเดตข้อมูลการแสดงผลเครดิต
	function updateCreditDisplay() {
		var sel = document.getElementById('payment');
		var selectedText = sel.options[sel.selectedIndex]?.text || '';
		var creditDays = selectedText.match(/\d+/) ? selectedText.match(/\d+/)[0] : '';

		var displayDays = document.getElementById('display_credit_days');
		if (displayDays) displayDays.value = creditDays || '30';

		var creditLimit = document.getElementById('credit_thb').value || '0';
		var formattedLimit = parseFloat(creditLimit.replace(/,/g, '')) || 0;
		var displayLimit = document.getElementById('display_credit_limit');
		if (displayLimit) displayLimit.value = formattedLimit.toLocaleString('en-US');
	}
</script>

<script>
	function object() {
		if (document.getElementById('object1').checked) {
			document.getElementById('dt1').style.display = 'block';
			document.getElementById('dt2').style.display = 'none';
			document.getElementById('dt3').style.display = 'none';
			document.getElementById('dt4').style.display = 'none';
		} else if (document.getElementById('object2').checked) {
			document.getElementById('dt1').style.display = 'none';
			document.getElementById('dt2').style.display = 'block';
			document.getElementById('dt3').style.display = 'none';
			document.getElementById('dt4').style.display = 'none';
		} else if (document.getElementById('object3').checked) {
			document.getElementById('dt1').style.display = 'none';
			document.getElementById('dt2').style.display = 'none';
			document.getElementById('dt3').style.display = 'block';
			document.getElementById('dt4').style.display = 'none';
		} else if (document.getElementById('object4').checked) {
			document.getElementById('dt1').style.display = 'none';
			document.getElementById('dt2').style.display = 'none';
			document.getElementById('dt3').style.display = 'none';
			document.getElementById('dt4').style.display = 'block';
		}

	}


	function ckk_1() {
		if (document.getElementById('object5').checked) {
			document.getElementById('dt5').style.display = 'block';
		} else if (document.getElementById('object6').checked) {
			document.getElementById('dt5').style.display = 'none';
		} else if (document.getElementById('object7').checked) {
			document.getElementById('dt5').style.display = 'none';
		}
	}



	function ckk_2() {
		if (document.getElementById('object8').checked) {
			document.getElementById('dv1').style.display = 'block';
			document.getElementById('dv2').style.display = 'none';
		} else if (document.getElementById('object9').checked) {
			document.getElementById('dv1').style.display = 'block';
			document.getElementById('dv2').style.display = 'none';
		} else if (document.getElementById('object10').checked) {
			document.getElementById('dv1').style.display = 'block';
			document.getElementById('dv2').style.display = 'none';
		} else if (document.getElementById('object11').checked) {
			document.getElementById('dv1').style.display = 'none';
			document.getElementById('dv2').style.display = 'block';
		}
	}
</script>

<style type="text/css">
	.button {
		background-color: #339900;
		border: none;
		color: white;
		padding: 8px 10px;
		text-align: center;
		text-decoration: none;
		display: inline-block;
		font-size: 16px;
		margin: 4px 2px;
		cursor: pointer;
	}

	.button1 {
		border-radius: 2px;
	}

	.button2 {
		border-radius: 4px;
	}

	.button3 {
		border-radius: 8px;
	}

	.button4 {
		border-radius: 12px;
	}

	.button5 {
		border-radius: 50%;
	}

	.style15 {
		font-size: 18px;
		color: #000000;
	}

	.style30 {
		font-size: 12px;
	}

	.style32 {
		font-size: 11px;
	}

	.style33 {
		font-size: 12px;
	}

	.style34 {
		color: #FF0000;
	}

	.style35 {
		font-size: 12px;
		color: #f2f2f2;
	}

	.style37 {
		color: #FF0000;
		font-size: 14px;
	}

	.style38 {
		color: #f2f2f2;
	}

	.style39 {
		font-size: 14px;
	}

	.style40 {
		font-size: 16px;
		color: #FF0000;
	}

	/* ================= MODERN PREMIUM THEME ================= */
	@import url('https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap');

	body {
		background-color: #F4EFF8 !important;
		background-image: none !important;
		font-family: 'Prompt', sans-serif !important;
		color: #4A4A4A !important;
	}

	/* Modern Header Layout */
	.so-header-container {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin: 0 auto;
		padding: 16px 0;
		width: 100%;
		max-width: 1096px;
		box-sizing: border-box;
	}

	.so-header-left {
		display: flex;
		flex-direction: column;
	}

	.so-title {
		font-size: 28px;
		font-weight: 600;
		color: #612989;
		margin: 0 0 8px 0;
	}

	.so-ref-info {
		display: flex;
		align-items: center;
		gap: 8px;
		font-size: 14px;
	}

	.so-ref-label {
		color: #8E8B94;
	}

	.so-ref-value {
		color: #612989;
		font-weight: 700;
	}

	.so-header-right {
		display: flex;
		gap: 12px;
	}

	/* Buttons */
	.btn-clear-loan-reserve {
		background-color: #F1E1FF;
		color: #612989;
		border: none;
		border-radius: 20px;
		padding: 10px 24px;
		font-size: 14px;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.2s ease;
		font-family: 'Prompt', sans-serif;
	}

	.btn-clear-loan-reserve:hover {
		background-color: #DCD6FA;
	}

	.btn-preview-so {
		background-color: #FFFFFF;
		color: #612989;
		border: 1px solid transparent;
		border-radius: 20px;
		padding: 10px 24px;
		font-size: 14px;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.2s ease;
		display: inline-flex;
		align-items: center;
		gap: 8px;
		font-family: 'Prompt', sans-serif;
		box-shadow: 0px 0px 4px 0px rgba(0, 0, 0, 0.25);
	}

	.btn-preview-so:hover {
		background-color: #FAF9FC;
		opacity: 0.9;
	}

	/* Tabs */
	.so-tabs-container {
		display: flex;
		background-color: transparent;
		margin-bottom: -1px;
		padding-left: 50px;
		position: relative;
	}

	.so-tab-btn {
		background: #FFFFFF;
		border: 1px solid #FFFFFF;
		border-bottom: none;
		outline: none;
		padding: 12px 28px;
		font-size: 14px;
		font-weight: 500;
		color: #3B3B3B;
		cursor: pointer;
		transition: all 0.2s ease;
		position: relative;
		/* margin-right: 6px; */
		font-family: 'Prompt', sans-serif;
	}

	.so-tab-btn:first-child {
		border-radius: 12px 0 0 0;
	}

	.so-tab-btn:last-child {
		border-radius: 0 12px 0 0;
	}

	.so-tab-btn:hover {
		color: #612989;
		background: #EFEBFF;
	}

	.so-tab-btn.active {
		color: #612989;
		font-weight: 500;
		font-size: 14px;
		background: #FFFFFF;
		padding-bottom: 11px;
	}

	.so-tab-btn.active::after {
		content: "";
		position: absolute;
		bottom: 0;
		left: 0;
		right: 0;
		height: 4px;
		background-color: #612989;
		border-radius: 25px;
	}

	/* Card Container */
	.so-card {
		background-color: #FFFFFF;
		border-radius: 10px;
		box-shadow: 0 4px 20px rgba(97, 41, 137, 0.04);
		padding: 32px;
		margin-bottom: 30px;
		border: 1px solid #EFEBEF;
		position: relative;
		width: 100%;
		max-width: 1096px;
		margin-left: auto;
		margin-right: auto;
		box-sizing: border-box;
	}

	/* Tab content */
	.so-tab-content {
		display: none;
	}

	.so-tab-content.active {
		display: block;
		background-color: transparent;
	}

	/* Grid Layouts */
	.so-grid-3 {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 24px;
		margin-bottom: 24px;
	}

	.so-grid-2 {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 24px;
		margin-bottom: 24px;
	}

	.so-grid-3-2-1 {
		display: grid;
		grid-template-columns: 2fr 1fr;
		gap: 24px;
		margin-bottom: 24px;
	}

	@media (max-width: 992px) {

		.so-grid-3,
		.so-grid-3-2-1 {
			grid-template-columns: repeat(2, 1fr);
		}
	}

	@media (max-width: 768px) {

		.so-grid-3,
		.so-grid-2,
		.so-grid-3-2-1 {
			grid-template-columns: 1fr;
			gap: 16px;
		}
	}

	/* Field styling */
	.so-field-group {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.so-label {
		font-size: 13px;
		font-weight: 500;
		color: #612989;
	}

	.so-label .required {
		color: #DC3545;
		margin-left: 2px;
	}

	.so-input {
		background-color: #F5F6F8;
		border: 1px solid transparent;
		border-radius: 10px;
		padding: 0 16px;
		font-size: 14px;
		color: #333333;
		font-family: 'Prompt', sans-serif;
		outline: none;
		transition: all 0.2s ease;
		height: 42px;
		line-height: 40px;
		box-sizing: border-box;
	}

	.so-input:focus {
		background-color: #FFFFFF;
		border-color: #612989;
		box-shadow: 0 0 0 3px rgba(97, 41, 137, 0.1);
	}

	.so-textarea {
		background-color: #F5F6F8;
		border: 1px solid transparent;
		border-radius: 12px;
		padding: 12px 16px;
		font-size: 14px;
		color: #333333;
		font-family: 'Prompt', sans-serif;
		outline: none;
		transition: all 0.2s ease;
		resize: vertical;
		box-sizing: border-box;
		width: 100%;
	}

	.so-textarea:focus {
		background-color: #FFFFFF;
		border-color: #612989;
		box-shadow: 0 0 0 3px rgba(97, 41, 137, 0.1);
	}

	/* Input Clear Wrapper */
	.so-input-wrapper {
		position: relative;
		display: flex;
	}

	.so-input-wrapper .so-input {
		width: 328px;
		padding-right: 40px;
	}

	/* fa-times clear icon inside wrappers */
	.so-input-wrapper>i.fas.fa-times {
		position: absolute;
		right: 12px;
		top: 50%;
		transform: translateY(-50%);
		cursor: pointer;
		color: #8E8B94;
		font-size: 14px;
		z-index: 1;
	}

	.so-input-clear {
		position: absolute;
		right: 12px;
		top: 50%;
		transform: translateY(-50%);
		background: transparent;
		border: none;
		color: #8E8B94;
		font-size: 20px;
		cursor: pointer;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 24px;
		height: 24px;
		border-radius: 50%;
	}

	.so-input-clear:hover {
		color: #333333;
		background-color: rgba(0, 0, 0, 0.05);
	}

	/* Select wrapper and select styling */
	.so-select-wrapper {
		position: relative;
		display: flex;
		width: 328px;
	}

	.so-select {
		width: 100%;
		background-color: #F5F6F8;
		border: 1px solid transparent;
		border-radius: 10px;
		padding: 0 32px 0 16px;
		font-size: 14px;
		color: #333333;
		font-family: 'Prompt', sans-serif;
		outline: none;
		transition: all 0.2s ease;
		height: 42px;
		line-height: 40px;
		appearance: none;
		-webkit-appearance: none;
		-moz-appearance: none;
		cursor: pointer;
		box-sizing: border-box;
	}

	.so-select:focus {
		background-color: #FFFFFF;
		border-color: #612989;
		box-shadow: 0 0 0 3px rgba(97, 41, 137, 0.1);
	}

	.so-select-wrapper::after {
		content: '\f078';
		font-family: 'Font Awesome 5 Free';
		font-weight: 900;
		position: absolute;
		right: 16px;
		top: 50%;
		transform: translateY(-50%);
		color: #8E8B94;
		pointer-events: none;
		font-size: 12px;
	}

	.so-select:disabled {
		opacity: 0.8;
		cursor: not-allowed;
	}

	/* Section Title */
	.so-section-title-container {
		margin: 32px 0 24px 0;
		border-bottom: 1px solid #EFEBEF;
		padding-bottom: 12px;
	}

	.so-section-title {
		font-size: 20px;
		font-weight: 500;
		color: #3B3B3B;
		margin: 0;
	}

	.so-divider {
		border: 2px solid #EDE9F0;
	}

	/* Custom Grid for Date & Toggles */
	.so-grid-3-custom {
		display: grid;
		grid-template-columns: 1fr 2fr;
		gap: 24px;
		margin-bottom: 24px;
	}

	@media (max-width: 768px) {
		.so-grid-3-custom {
			grid-template-columns: 1fr;
			gap: 16px;
		}
	}

	.so-toggle-group {
		display: flex;
		align-items: flex-end;
		gap: 12px;
		height: 48px;
		/* Align with input height */
	}

	@media (max-width: 768px) {
		.so-toggle-group {
			height: auto;
			flex-wrap: wrap;
		}
	}

	/* Date picker calendar icon placement */
	.calendar-wrapper {
		position: relative;
	}

	.calendar-wrapper::after {
		content: '';
		background-image: url('img/icons/calendar.png');
		background-size: contain;
		background-repeat: no-repeat;
		background-position: center;
		position: absolute;
		right: 16px;
		top: 50%;
		transform: translateY(-50%);
		pointer-events: none;
		width: 18px;
		height: 18px;
	}

	.calendar-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		width: auto;
		height: auto;
		color: transparent;
		background: transparent;
		cursor: pointer;
	}

	/* Time picker clock icon placement */
	.time-wrapper {
		position: relative;
		display: flex;
		align-items: center;
		width: 100%;
	}

	.time-wrapper::after {
		content: '';
		background-image: url('img/icons/clock.png');
		background-size: contain;
		background-repeat: no-repeat;
		background-position: center;
		position: absolute;
		right: 16px;
		top: 50%;
		transform: translateY(-50%);
		pointer-events: none;
		width: 18px;
		height: 18px;
	}

	.time-wrapper input[type="time"]::-webkit-calendar-picker-indicator {
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		width: auto;
		height: auto;
		color: transparent;
		background: transparent;
		cursor: pointer;
	}

	/* Checklist layout for document attachments */
	.so-checkbox-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
		gap: 12px;
		margin-bottom: 24px;
	}

	.so-checkbox-pill {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		padding: 12px 16px;
		background-color: #F8F7FC;
		border-radius: 10px;
		font-size: 14px;
		font-weight: 500;
		color: #4A4A4A;
		cursor: pointer;
		transition: all 0.2s ease;
		border: 1px solid #EFEBEF;
	}

	.so-checkbox-pill:hover {
		background-color: #F1EDFA;
		border-color: #D3C9FC;
	}

	.so-checkbox-pill input[type="checkbox"] {
		width: 18px;
		height: 18px;
		accent-color: #612989;
		cursor: pointer;
		margin: 0;
	}

	/* Pills styling for extra documents tab */
	.so-doc-pill {
		display: flex;
		align-items: center;
		justify-content: center;
		background-color: #F4F3F7;
		color: #5f5f5f;
		border-radius: 8px;
		padding: 10px 16px;
		font-size: 14px;
		font-weight: 400;
		cursor: pointer;
		transition: all 0.2s ease;
		text-align: center;
		user-select: none;
		height: 42px;
		box-sizing: border-box;
	}

	.so-doc-pill:hover {
		background-color: #EFEBFF;
		color: #612989;
	}

	.so-doc-pill input[type="checkbox"] {
		display: none;
	}

	.so-doc-pill:has(input:checked) {
		background-color: #612989;
		color: #FFFFFF;
	}

	.so-doc-pill:has(input:checked):hover {
		background-color: #502073;
		color: #FFFFFF;
	}

	.so-doc-grid {
		display: grid;
		grid-template-columns: repeat(6, 1fr);
		gap: 16px;
		margin-bottom: 24px;
		align-items: end;
	}

	@media (max-width: 992px) {
		.so-doc-grid {
			grid-template-columns: repeat(3, 1fr);
		}

		.so-doc-grid>.so-doc-pill {
			grid-column: span 1 !important;
		}

		.so-doc-grid>.so-doc-other-wrapper {
			grid-column: span 3 !important;
		}
	}

	@media (max-width: 576px) {
		.so-doc-grid {
			grid-template-columns: 1fr;
		}

		.so-doc-grid>.so-doc-pill {
			grid-column: span 1 !important;
		}

		.so-doc-grid>.so-doc-other-wrapper {
			grid-column: span 1 !important;
		}
	}

	.so-checkbox-pill-other {
		display: flex;
		align-items: center;
		gap: 10px;
		grid-column: span 2;
	}

	@media (max-width: 768px) {
		.so-checkbox-pill-other {
			grid-column: span 1;
			flex-direction: column;
			align-items: stretch;
		}
	}

	/* Radio Group */
	.so-radio-group-vertical {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}

	.so-radio-label {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		font-size: 14px;
		color: #4A4A4A;
		cursor: pointer;
	}

	.so-radio-label input[type="radio"] {
		width: 18px;
		height: 18px;
		accent-color: #612989;
		cursor: pointer;
		margin: 0;
	}

	/* File Upload input */
	.so-file-uploads {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	.so-file-input-wrapper {
		background-color: #F8F7FC;
		border: 1px dashed #D3C9FC;
		border-radius: 10px;
		padding: 8px 12px;
		font-size: 13px;
		transition: all 0.2s ease;
	}

	.so-file-input-wrapper:hover {
		background-color: #F1EDFA;
	}

	.so-file-input {
		font-family: 'Prompt', sans-serif;
		cursor: pointer;
	}

	/* Fieldset and legend */
	.so-fieldset {
		border: 1px solid #EFEBEF;
		border-radius: 12px;
		padding: 24px;
		margin-bottom: 24px;
	}

	.so-legend {
		font-size: 15px;
		font-weight: 600;
		color: #612989;
		padding: 0 10px;
	}

	/* Buttons footer */
	.so-footer-buttons {
		display: flex;
		justify-content: center;
		margin-top: 30px;
	}

	.btn-submit-so {
		background-color: #612989;
		color: #FFFFFF;
		border: none;
		border-radius: 24px;
		padding: 12px 48px;
		font-size: 16px;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.2s ease;
		box-shadow: 0 4px 12px rgba(97, 41, 137, 0.2);
		font-family: 'Prompt', sans-serif;
	}

	.btn-submit-so:hover {
		background-color: #502173;
		box-shadow: 0 6px 16px rgba(97, 41, 137, 0.3);
	}

	/* Collapsible Section styles */
	.collapsible-clear-section {
		max-height: 0;
		overflow: hidden;
		transition: max-height 0.3s ease-out;
		margin-bottom: 0;
	}

	.collapsible-clear-section.show {
		max-height: 500px;
		margin-bottom: 24px;
	}

	/* Bottom tabs */
	.bottom-tabs-container {
		display: flex;
		border-bottom: 2px solid #EFEBEF;
		margin-bottom: 24px;
		gap: 8px;
	}

	.bottom-tab-btn {
		background: transparent;
		border: none;
		outline: none;
		padding: 10px 20px;
		font-size: 15px;
		font-weight: 500;
		color: #8E8B94;
		cursor: pointer;
		text-decoration: none !important;
		border-bottom: 3px solid transparent;
		transition: all 0.2s ease;
		font-family: 'Prompt', sans-serif;
	}

	.bottom-tab-btn:hover {
		color: #612989;
	}

	.bottom-tab-btn.active {
		color: #612989;
		font-weight: 600;
		border-bottom: 3px solid #612989;
	}

	/* Checkbox/Toggle styling for modern pills */
	.so-toggle-pill {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background-color: #F1F0F5;
		color: #495057;
		padding: 10px 24px;
		border-radius: 10px;
		font-size: 14px;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.2s ease;
		user-select: none;
		border: 1px solid transparent;
		min-width: 120px;
		text-align: center;
		font-family: 'Prompt', sans-serif;
		box-sizing: border-box;
		height: 42px;
	}

	.so-toggle-pill input[type="checkbox"] {
		display: none;
	}

	.so-toggle-pill.active,
	.so-toggle-pill:has(input[type="checkbox"]:checked) {
		background-color: #612989;
		color: #FFFFFF;
		border-color: #612989;
		font-weight: 500;
	}

	.so-toggle-pill-outline {
		background-color: #FFFFFF;
		color: #612989;
		border: 1px solid #EFEBEF;
		border-radius: 24px;
	}

	.so-toggle-pill-outline.active,
	.so-toggle-pill-outline:has(input[type="checkbox"]:checked) {
		background-color: #EFEBFF;
		color: #612989;
		border-color: #612989;
	}

	/* Autocomplete style tweaks for integration */
	.autocomplete {
		z-index: 9999 !important;
	}

	.so-input-with-checkbox {
		display: flex;
		align-items: center;
		gap: 12px;
		width: 100%;
	}

	.so-checkbox-label {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: 14px;
		font-weight: 500;
		color: #4A4A4A;
		cursor: pointer;
		white-space: nowrap;
	}

	.so-checkbox-label input[type="checkbox"] {
		width: 18px;
		height: 18px;
		accent-color: #612989;
		cursor: pointer;
		margin: 0;
	}

	/* Radio Button Styling */
	.so-payment-modes {
		display: flex;
		gap: 32px;
		margin-bottom: 24px;
	}

	.so-payment-radio-label {
		display: inline-flex;
		align-items: center;
		gap: 12px;
		font-size: 15px;
		font-weight: 500;
		color: #4A4A4A;
		cursor: pointer;
		user-select: none;
	}

	.so-payment-radio-label input[type="radio"] {
		appearance: none;
		-webkit-appearance: none;
		width: 22px;
		height: 22px;
		border: 2px solid #E2DCE8;
		border-radius: 50%;
		outline: none;
		margin: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.2s ease;
		background-color: #FFFFFF;
	}

	.so-payment-radio-label input[type="radio"]::before {
		content: "";
		width: 10px;
		height: 10px;
		border-radius: 50%;
		background-color: #612989;
		transform: scale(0);
		transition: transform 0.2s ease;
	}

	.so-payment-radio-label input[type="radio"]:checked {
		border-color: #612989;
	}

	.so-payment-radio-label input[type="radio"]:checked::before {
		transform: scale(1);
	}

	.so-payment-radio-label:has(input[type="radio"]:checked) {
		color: #612989;
	}

	/* Customer Info custom grid */
	.so-customer-grid {
		display: grid;
		grid-template-columns: 2fr 1fr;
		gap: 24px;
		margin-bottom: 24px;
	}

	@media (max-width: 768px) {
		.so-customer-grid {
			grid-template-columns: 1fr;
			gap: 16px;
		}
	}

	.so-customer-left {
		display: flex;
		flex-direction: column;
		gap: 24px;
	}

	.so-customer-right-box {
		width: 680px;
		margin: 27px 10px 0 0;
		background-color: #F1F0F5;
		border-radius: 10px;
		display: flex;
		align-items: center;
		justify-content: center;
		box-sizing: border-box;
	}

	.btn-add-customer {
		background-color: #FFFFFF;
		color: #612989;
		border: 1px solid #EFEBEF;
		border-radius: 24px;
		padding: 12px 32px;
		font-size: 14px;
		font-weight: 600;
		cursor: pointer;
		display: inline-flex;
		align-items: center;
		gap: 8px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
		font-family: 'Prompt', sans-serif;
		transition: all 0.2s ease;
	}

	.btn-add-customer:hover {
		background-color: #EFEBFF;
		border-color: #612989;
		transform: translateY(-1px);
	}

	.search-wrapper {
		position: relative;
	}

	.search-wrapper::after {
		content: '\f002';
		font-family: 'Font Awesome 5 Free';
		font-weight: 900;
		position: absolute;
		right: 16px;
		top: 50%;
		transform: translateY(-50%);
		color: #8E8B94;
		pointer-events: none;
		font-size: 16px;
	}

	/* Customer top grid layout to match new design */
	.so-customer-top-grid {
		display: grid;
		grid-template-columns: 328px 1fr;
		gap: 24px;
		margin-bottom: 24px;
	}

	@media (max-width: 768px) {
		.so-customer-top-grid {
			grid-template-columns: 1fr;
			gap: 16px;
		}
	}

	.so-customer-top-left {
		display: flex;
		flex-direction: column;
		gap: 24px;
		justify-content: space-between;
	}

	.so-customer-pills-row {
		display: flex;
		gap: 16px;
		align-items: center;
		height: 42px;
		margin-bottom: 4px;
	}

	/* Special pill buttons styling for customer section */
	.btn-add-customer-pill {
		background-color: #FFFFFF;
		color: #612989;
		border: 1px solid #EFEBEF;
		border-radius: 24px;
		height: 42px;
		padding: 0 24px;
		font-size: 14px;
		font-weight: 500;
		cursor: pointer;
		display: inline-flex;
		align-items: center;
		gap: 8px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
		font-family: 'Prompt', sans-serif;
		transition: all 0.2s ease;
		box-sizing: border-box;
	}

	.btn-add-customer-pill:hover {
		background-color: #EFEBFF;
		border-color: #612989;
	}

	.so-toggle-pill-outline-custom {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background-color: #FFFFFF;
		color: #612989;
		border: 1px solid #EFEBEF;
		border-radius: 24px;
		height: 42px;
		padding: 0 24px;
		font-size: 14px;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.2s ease;
		min-width: 120px;
		text-align: center;
		font-family: 'Prompt', sans-serif;
		box-sizing: border-box;
		user-select: none;
	}

	.so-toggle-pill-outline-custom input[type="checkbox"] {
		display: none;
	}

	.so-toggle-pill-outline-custom.active,
	.so-toggle-pill-outline-custom:has(input[type="checkbox"]:checked) {
		background-color: #EFEBFF;
		color: #612989;
		border-color: #612989;
	}

	.so-toggle-pill-outline-custom span {
		display: inline-flex;
		align-items: center;
		gap: 6px;
	}

	.so-customer-top-right {
		display: flex;
		flex-direction: column;
	}

	.so-customer-top-right .so-textarea {
		height: 118px;
		resize: none;
	}
</style>

<body>
	<?php

	$yearMonth = substr(date("Y") + 543, -2) . date("m");
	$sql = "SELECT MAX(ref_id) AS MAXID FROM hos__so";
	$qry = mysqli_query($conn, $sql) or die(mysqli_error());
	$rs = mysqli_fetch_assoc($qry);
	$maxId = substr($rs['MAXID'], -4);
	$maxId3 = substr($rs['MAXID'], -8);

	$maxId1 = substr($maxId3, 0, -4);
	$so = "SO";

	if ($maxId1 == $yearMonth) {
		$maxId1 = ($maxId + 1);
		$maxId2 = substr("00000" . $maxId1, -4);
		$nextId = $yearMonth . $maxId2;
	} else {
		$maxId1 = "0001";
		$nextId = $yearMonth . $maxId1;
	}





	?>

	<!--action="register_office1.php"-->
	<form action='register_suphos1.php' method="post" name="frmMain" enctype="multipart/form-data" onSubmit="JavaScript:return fncSubmit();">

		<script language="javascript">
			function fncSubmit() //ห้ามชื่อสินค้า ยี่ห้อสินค้า รุ่นสินค้าเป็
			{
				if (document.frmMain.payment.value != "") {
					if (document.frmMain.payment.value == "7") {


						if (document.frmMain.date_tranfer.value == "") {

							alert('กรุณาใส่วันที่โอน');
							document.frmMain.date_tranfer.focus();
							return false;
						}
					}
				}

				if (document.frmMain.start_time.value == "") {

					alert('กรุณาใส่เวลาส่ง');
					document.frmMain.start_time.focus();
					return false;
				}

				if (document.frmMain.customer_name.value == "") {
					alert('กรุณาใส่ชื่อลูกค้า');
					document.frmMain.customer_name.focus();
					return false;
				}

				if (document.frmMain.customer_tel.value == "") {
					alert('กรุณาใส่เบอร์โทรลูกค้า');
					document.frmMain.customer_tel.focus();
					return false;
				}
				if (document.frmMain.address_1.value == "") {
					alert('กรุณาใส่สถานที่ส่งสินค้า');
					document.frmMain.address_1.focus();
					return false;
				}

				if (document.frmMain.address_name.value == "") {
					alert('กรุณาใส่ที่อยู่ในการส่งสินค้า');
					document.frmMain.address_name.focus();
					return false;
				}

				if (document.frmMain.address_send.value == "") {
					alert('กรุณาใส่สถานที่ติดตั้งเครื่อง');
					document.frmMain.address_send.focus();
					return false;
				}

				if (document.frmMain.h_employee_name.value == "") {
					alert('กรุณาเลือกชื่อพนักงาน');
					document.frmMain.employee_name.focus();
					return false;
				}

				if (document.frmMain.province_name.value == "") {
					alert('กรุณาเลือกจังหวัดที่ต้องการจัดส่ง');
					document.frmMain.province_name.focus();
					return false;
				}

				document.frmMain.submit();
			}
		</script>

		<div class="w3-container" style="max-width: 1200px; margin: 0 auto;"><!-- main div -->

			<!-- Header Section -->
			<div class="so-header-container">
				<div class="so-header-left">
					<h1 class="so-title">Register Sale Order</h1>
					<div class="so-ref-info">
						<span class="so-ref-label">เลขที่อ้างอิง</span>
						<span class="so-ref-value"><?php echo $so . $nextId; ?></span>
					</div>

				</div>
				<div class="so-header-right">
					<button type="button" class="btn-clear-loan-reserve" onclick="toggleClearSection()">เคลียร์ยืม/จอง</button>
					<button type="button" class="btn-preview-so" onclick="window.print();"><img src="img/icons/preview.png" alt="preview" style="width: 16px; height: 16px;"> Preview</button>
				</div>
			</div>

			<!-- Tab buttons -->
			<div class="so-tabs-container">
				<button type="button" class="so-tab-btn active" onclick="switchSoTab(event, 'tab-document-info')">ข้อมูลเอกสาร</button>
				<button type="button" class="so-tab-btn" onclick="switchSoTab(event, 'tab-admin-info')">Admin</button>
			</div>

			<input type="hidden" name="ref_id" value="<?php echo $ffirst['ref_id'] + 1; ?>">

			<!-- Card Container -->
			<div>

				<!-- TAB 1: ข้อมูลเอกสาร -->
				<div id="tab-document-info" class="so-tab-content active">

					<!-- เคลียร์ยืม/จอง Section (hidden by default) -->
					<div class="so-card">
						<!-- <div id="clear_loan_reserve_section" class="collapsible-clear-section">
							<div style="background-color: #FAF9FC; border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 1px dashed #D3C9FC;">
								<h3 class="so-section-sub-title" style="color: #612989; margin: 0 0 16px 0; font-size: 16px; font-weight: 600;">เคลียร์ยืม/จอง</h3>
								<div class="so-grid-3">
									<div class="so-field-group">
										<div class="so-input-with-checkbox">
											<label class="so-checkbox-label">
												<input type="checkbox" name="book_clear" value="1"> เคลียร์ใบจอง :
											</label>
											<input name="book_no" class="so-input" placeholder="เลขที่..." style="flex: 1;">
										</div>
									</div>
									<div class="so-field-group">
										<div class="so-input-with-checkbox">
											<label class="so-checkbox-label">
												<input type="checkbox" name="brn_clear" value="1"> เคลียร์ใบยืมสินค้า ติดเล่ม :
											</label>
											<input name="brn_no" class="so-input" placeholder="เลขที่..." style="flex: 1;">
										</div>
									</div>
									<div class="so-field-group">
										<div class="so-input-with-checkbox">
											<label class="so-checkbox-label">
												<input type="checkbox" name="brnp_clear" value="1"> เคลียร์ใบยืมสินค้า กระดาษต่อเนื่อง :
											</label>
											<input name="brnp_no" class="so-input" placeholder="เลขที่..." style="flex: 1;">
										</div>
									</div>
								</div>
							</div>
						</div> -->

						<div class="so-grid-3">
							<!-- บริษัท* -->
							<div class="so-field-group">
								<label class="so-label">บริษัท<span class="required">*</span></label>
								<div class="so-select-wrapper">
									<!-- Keeping original radio checked and named type_doc hidden so it submits correctly -->
									<input type="radio" checked='checked' name="type_doc" value="3" style="display:none;">
									<select class="so-select">
										<option value="3">AWL</option>
										<option value="3">NBM</option>
									</select>
								</div>
							</div>

							<!-- ประเภท* -->
							<div class="so-field-group">
								<label class="so-label">ประเภท<span class="required">*</span></label>
								<div class="so-select-wrapper">
									<select class="so-select" onchange="
									document.getElementById('ic_ckk').checked = false;
									document.getElementById('et_ckk').checked = false;
									if(this.value == '2') document.getElementById('et_ckk').checked = true;
									if(this.value == '3') document.getElementById('ic_ckk').checked = true;
								">
										<option value="1">ใบสั่งขาย</option>
										<option value="2">ใบสั่งขาย E-Tax</option>
										<option value="3">ใบฝากขาย (IC)</option>
									</select>
								</div>
							</div>

							<!-- E-Mail* -->
							<div class="so-field-group">
								<label class="so-label">E-Mail<span class="required">*</span></label>
								<div class="so-input-wrapper">
									<input type="text" name="email" id="email" class="so-input" placeholder="example@email.com" style="padding-right: 32px;">
									<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('email').value=''"></i>
								</div>
							</div>
						</div>

						<div class="so-grid-3">
							<!-- แผนก/เขตการขาย* -->
							<div class="so-field-group">
								<label class="so-label">แผนก/เขตการขาย<span class="required">*</span></label>
								<div class="so-select-wrapper">
									<select class="so-select">
										<option value="">เลือกแผนก/เขตการขาย</option>
									</select>
								</div>
							</div>

							<!-- ช่องทางการขาย* -->
							<div class="so-field-group">
								<label class="so-label">ช่องทางการขาย<span class="required">*</span></label>
								<div class="so-select-wrapper">
									<select class="so-select">
										<option value="">เลือกช่องทางการขาย</option>
									</select>
								</div>
							</div>

							<!-- Extra document options that were next to type_doc in original: ใบฝากขาย, ขอบิล E-Tax (Hidden since it's now in the dropdown) -->
							<div class="so-field-group" style="display: none;">
								<div style="display: flex; gap: 16px; align-items: center; height: 48px;">
									<label class="so-checkbox-label">
										<input type="checkbox" name="ic_ckk" id="ic_ckk" value="1">
										<span style="color: #612989; font-weight: 600;">ใบฝากขาย</span>
									</label>
									<label class="so-checkbox-label">
										<input type="checkbox" name="et_ckk" id="et_ckk" value="1">
										<span style="color: #612989; font-weight: 600;">ขอบิล E-Tax</span>
									</label>
								</div>
							</div>
						</div>

						<!-- Section: ข้อมูลเอกสาร -->
						<div class="so-section-title-container">
							<h2 class="so-section-title">ข้อมูลเอกสาร</h2>
							<hr class="so-divider">
						</div>

						<?php
						date_default_timezone_set("Asia/Bangkok");
						$month = date('m');
						$day = date('d');
						$year = date('Y');
						$today = $year . '-' . $month . '-' . $day;
						?>

						<div class="so-grid-3-custom">
							<!-- วันที่ -->
							<div class="so-field-group">
								<label class="so-label">วันที่</label>
								<div class="so-input-wrapper calendar-wrapper">
									<input type="date" name="date_so" id="date_so" value="<?php echo $today; ?>" class="so-input">
								</div>
							</div>

							<!-- Toggles: งานด่วน, ออเดอร์ฝาก, ไม่ได้ประมาณการ -->
							<div class="so-field-group">
								<label class="so-label">&nbsp;</label>
								<div class="so-toggle-group">
									<label class="so-toggle-pill" id="lbl-que_ckk">
										<input type="checkbox" name="que_ckk" id="que_ckk" value="1">
										<span>งานด่วน</span>
									</label>
									<label class="so-toggle-pill" id="lbl-have_order">
										<input type="checkbox" name="have_order" id="have_order" value="1">
										<span>ออเดอร์ฝาก</span>
									</label>
									<label class="so-toggle-pill" id="lbl-plan_ckk">
										<input type="checkbox" name="plan_ckk" id="plan_ckk" value="1">
										<span>ไม่ได้ประมาณการ</span>
									</label>
								</div>
							</div>
						</div>

						<div class="so-grid-3">
							<!-- เลขที่ใบสั่งซื้อ/สัญญา -->
							<div class="so-field-group">
								<label class="so-label">เลขที่ใบสั่งซื้อ/สัญญา</label>
								<input name="po_no" class="so-input" placeholder="เช่น lv58945820965">
							</div>

							<!-- วันที่กำหนดส่งตามสัญญา* -->
							<div class="so-field-group">
								<label class="so-label">วันที่กำหนดส่งตามสัญญา<span class="required">*</span></label>
								<div class="so-input-wrapper calendar-wrapper">
									<input name="delivery_contract" type='date' id="delivery_contract" class="so-input" placeholder="กรุณาระบุเป็นวันที่เท่านั้น !!!">
								</div>
							</div>

							<!-- เลขที่ใบงานบริการ -->
							<div class="so-field-group">
								<label class="so-label">เลขที่ใบงานบริการ</label>
								<input name="cm_no" id="cm_no" class="so-input" placeholder="เช่น 932813829r7">
							</div>
						</div>
					</div>

				</div><!-- End TAB 1 -->

				<!-- TAB 2: Admin -->
				<div id="tab-admin-info" class="so-tab-content">
					<style>
						/* .admin-ui-card {
					background-color: #FFFFFF;
					border-radius: 16px;
					padding: 32px;
					margin-bottom: 30px;
					border: 1px solid #E5E7EB;
					font-family: 'Prompt', sans-serif;
				} */

						.admin-ui-title {
							font-size: 20px;
							font-weight: 500;
							color: #3B3B3B;
							margin: 0 0 24px 0;
							padding-bottom: 16px;
							border-bottom: 1px solid #EFEBEF;
						}

						.admin-ui-grid {
							display: grid;
							grid-template-columns: repeat(4, 1fr);
							gap: 24px;
							margin-bottom: 24px;
						}

						@media (max-width: 992px) {
							.admin-ui-grid {
								grid-template-columns: repeat(2, 1fr);
							}

							.admin-ui-field.span-3 {
								grid-column: span 2;
							}
						}

						@media (max-width: 768px) {
							.admin-ui-grid {
								grid-template-columns: 1fr;
							}

							.admin-ui-field.span-3 {
								grid-column: span 1;
							}
						}

						.admin-ui-field {
							display: flex;
							flex-direction: column;
							gap: 8px;
						}

						.admin-ui-field.span-3 {
							grid-column: span 3;
						}

						.admin-ui-label {
							font-size: 14px;
							font-weight: 400;
							color: #6E3CBC;
						}

						.admin-ui-input-wrapper {
							position: relative;
							display: flex;
							align-items: center;
						}

						.admin-ui-input {
							width: 100%;
							background-color: #F5F5F7;
							border: 1px solid transparent;
							border-radius: 12px;
							padding: 0 16px;
							font-size: 16px;
							font-weight: 400;
							color: #612989;
							font-family: 'Prompt', sans-serif;
							outline: none;
							height: 48px;
							box-sizing: border-box;
							transition: all 0.2s ease;
						}

						.admin-ui-input:focus {
							background-color: #FFFFFF;
							border-color: #6E3CBC;
							box-shadow: 0 0 0 3px rgba(110, 60, 188, 0.1);
						}

						.admin-ui-input.has-icon {
							padding-right: 48px;
						}

						.admin-ui-icon {
							position: absolute;
							right: 16px;
							color: #3B3B3B;
							font-size: 18px;
							pointer-events: none;
						}

						.admin-ui-icon-clickable {
							position: absolute;
							right: 16px;
							color: #8E8B94;
							font-size: 16px;
							cursor: pointer;
						}

						.admin-ui-btn {
							background-color: #F1E1FF;
							color: #612989;
							border: none;
							border-radius: 24px;
							height: 48px;
							padding: 0 24px;
							font-size: 16px;
							font-weight: 500;
							cursor: pointer;
							display: flex;
							align-items: center;
							justify-content: center;
							gap: 8px;
							font-family: 'Prompt', sans-serif;
							margin-top: 25px;
						}
					</style>

					<div class="so-card">
						<h2 class="admin-ui-title">ข้อมูลเพิ่มเติม (Admin)</h2>

						<!-- Row 1 -->
						<div class="admin-ui-grid" style="margin-bottom: 24px;">
							<div class="admin-ui-field">
								<label class="admin-ui-label">เลขที่เอกสาร</label>
								<div class="admin-ui-input-wrapper">
									<input type="text" name="admin_doc_no" class="admin-ui-input" placeholder="No.">
								</div>
							</div>
							<div class="admin-ui-field">
								<button type="button" class="admin-ui-btn">
									<img src="img/icons/doc.png" alt="doc" style="width: 16px; height: 16px;"> Run เอกสาร
								</button>
							</div>
							<div class="admin-ui-field">
								<label class="admin-ui-label">เลขที่ลงงาน</label>
								<div class="admin-ui-input-wrapper">
									<input type="text" name="admin_work_no" class="admin-ui-input has-icon" value="3149713948">
									<i class="fas fa-search admin-ui-icon"></i>
								</div>
							</div>
							<div class="admin-ui-field">
								<label class="admin-ui-label">เลขที่ SR ลดหนี้</label>
								<div class="admin-ui-input-wrapper">
									<input type="text" name="admin_sr_no" class="admin-ui-input has-icon" value="4732981308">
									<i class="fas fa-search admin-ui-icon"></i>
								</div>
							</div>
						</div>

						<!-- Row 2 -->
						<div class="admin-ui-grid" style="margin-bottom: 24px;">
							<div class="admin-ui-field">
								<label class="admin-ui-label">เลขที่ใบฝาก</label>
								<div class="admin-ui-input-wrapper">
									<input type="text" name="admin_deposit_no" class="admin-ui-input has-icon" value="3124791749">
									<i class="fas fa-search admin-ui-icon"></i>
								</div>
							</div>
							<div class="admin-ui-field">
								<label class="admin-ui-label">วันที่ออกเอกสาร</label>
								<div class="admin-ui-input-wrapper">
									<input type="text" name="admin_doc_date" class="admin-ui-input has-icon" value="09/09/2568">
									<i class="far fa-calendar-alt admin-ui-icon"></i>
								</div>
							</div>
							<div class="admin-ui-field">
								<label class="admin-ui-label">จำนวนกล่อง</label>
								<div class="admin-ui-input-wrapper">
									<input type="text" name="admin_box_count" class="admin-ui-input" placeholder="เฉพาะตัวเลข">
								</div>
							</div>
							<div class="admin-ui-field">
								<label class="admin-ui-label">จำนวนครั้งที่แก้ไขบิล</label>
								<div class="admin-ui-input-wrapper">
									<input type="text" name="admin_edit_count" class="admin-ui-input" placeholder="ใส่เฉพาะตัวเลข">
								</div>
							</div>
						</div>

						<!-- Row 3 -->
						<div class="admin-ui-grid" style="margin-bottom: 24px;">
							<div class="admin-ui-field">
								<label class="admin-ui-label">วันที่ออกเอกสาร (เดิม)</label>
								<div class="admin-ui-input-wrapper">
									<input type="text" name="admin_old_doc_date" class="admin-ui-input has-icon" value="09/09/2568">
									<i class="far fa-calendar-alt admin-ui-icon"></i>
								</div>
							</div>
							<div class="admin-ui-field span-3">
								<label class="admin-ui-label">สาเหตุการแก้ไขบิล</label>
								<div class="admin-ui-input-wrapper">
									<input type="text" name="admin_edit_reason" class="admin-ui-input has-icon" value="">
									<i class="fas fa-times admin-ui-icon-clickable" onclick="this.previousElementSibling.value=''"></i>
								</div>
							</div>
						</div>

						<!-- Row 4 -->
						<div class="admin-ui-grid">
							<div class="admin-ui-field">
								<button type="button" class="admin-ui-btn">
									<img src="img/icons/circle_x.png" alt="circle_x" style="width: 16px; height: 16px;"> ยกเลิกเอกสาร
								</button>
							</div>
							<div class="admin-ui-field span-3">
								<label class="admin-ui-label">หมายเหตุการยกเลิก</label>
								<div class="admin-ui-input-wrapper">
									<input type="text" name="admin_cancel_reason" class="admin-ui-input has-icon" value="">
									<i class="fas fa-times admin-ui-icon-clickable" onclick="this.previousElementSibling.value=''"></i>
								</div>
							</div>
						</div>

					</div>

					<!-- <fieldset class="so-fieldset">
				<legend class="so-legend">หมายเหตุแจ้งแผนกที่เกี่ยวข้อง</legend>

				<div class="so-field-group" style="margin-bottom: 16px;">
					<label class="so-label">จัดส่ง</label>
					<textarea name="comment_cs" class="so-textarea" id="comment_cs" rows="3" placeholder="ข้อความแจ้งฝ่ายจัดส่ง..."></textarea>
				</div>

				<div class="so-field-group" style="margin-bottom: 16px;">
					<label class="so-label">ช่าง</label>
					<textarea name="comment_en" class="so-textarea" id="comment_en" rows="3" placeholder="ข้อความแจ้งฝ่ายช่าง..."></textarea>
				</div>

				<div class="so-field-group" style="margin-bottom: 16px;">
					<label class="so-label">คลังสินค้า</label>
					<textarea name="comment_st" class="so-textarea" id="comment_st" rows="3" placeholder="ข้อความแจ้งฝ่ายคลังสินค้า..."></textarea>
				</div>

				<div class="so-field-group" style="margin-bottom: 8px;">
					<label class="so-label">Admin</label>
					<textarea name="comment_ad" class="so-textarea" id="comment_ad" rows="3" placeholder="ข้อความแจ้ง Admin..."></textarea>
				</div>
			</fieldset> -->
				</div>
				<!-- End TAB 2 -->

				<div class="so-card">
					<!-- Section: ข้อมูลลูกค้า -->
					<div class="so-section-title-container">
						<h2 class="so-section-title">ข้อมูลลูกค้า</h2>
						<hr class="so-divider">
					</div>

					<div class="so-customer-top-grid">
						<div class="so-customer-top-left">
							<!-- Pills Row: Add Customer & Bill Info Toggle -->
							<div class="so-customer-pills-row">
								<button type="button" class="btn-add-customer-pill" onclick="window.open('add_customer.php', '_blank', 'width=1000,height=600');">
									<img src="img/icons/add_user.png" alt="add_user" style="width: 23px;"> ข้อมูลลูกค้า
								</button>
								<label class="so-toggle-pill-outline-custom" id="lbl-full_bill">
									<input type="checkbox" name="full_bill" id="full_bill" value="1">
									<span><img src="img/icons/user_card.png" alt="user_card" style="width: 23px;"> ข้อมูลออกบิล</span>
								</label>
							</div>

							<!-- เลขประจำตัวผู้เสียภาษี -->
							<div class="so-field-group">
								<label class="so-label">เลขประจำตัวผู้เสียภาษี<span class="required">*</span></label>
								<div class="so-input-wrapper">
									<input type="text" name="tax_id" id="tax_id" class="so-input" placeholder="เลขประจำตัวผู้เสียภาษี..." style="padding-right: 32px;">
									<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('tax_id').value=''"></i>
								</div>
							</div>
						</div>

						<div class="so-customer-top-right">
							<!-- ค้นหาลูกค้า (ข้อมูลลูกค้า) -->
							<div class="so-field-group" style="height: 100%;">
								<label class="so-label">ข้อมูลลูกค้า</label>
								<style>
									.customer-info-display-card {
										background-color: #F4F5F7;
										border-radius: 12px;
										padding: 24px;
										display: flex;
										gap: 48px;
										font-family: 'Prompt', sans-serif;
										width: 100%;
										box-sizing: border-box;
									}

									.cidc-col {
										flex: 1;
										display: flex;
										flex-direction: column;
										gap: 16px;
									}

									.cidc-row {
										display: flex;
										align-items: center;
									}

									.cidc-label {
										width: 100px;
										font-size: 14px;
										color: #6C757D;
										font-weight: 400;
									}

									.cidc-value {
										flex: 1;
										font-size: 14px;
										color: #3B3B3B;
										display: flex;
										align-items: center;
									}

									.cidc-value-input {
										background: transparent;
										border: none;
										outline: none;
										font-size: 14px;
										color: #3B3B3B;
										width: 100%;
										font-family: 'Prompt', sans-serif;
										resize: none;
										padding: 0;
										margin: 0;
									}

									.cidc-value-input.purple-text {
										color: #612989;
									}

									.cidc-value-input.underline {
										text-decoration: underline;
									}

									.cidc-status-icon {
										color: #612989;
										margin-right: 8px;
										font-size: 16px;
									}
								</style>

								<div class="customer-info-display-card">
									<!-- Column 1 -->
									<div class="cidc-col">
										<div class="cidc-row">
											<div class="cidc-label">รหัสลูกค้า</div>
											<div class="cidc-value">
												<textarea name="bill_id" id="bill_id" class="cidc-value-input" style="height: 24px; line-height: 24px;" readonly placeholder="" OnChange="JavaScript:doCallAjax1('bill_id','bill_name','bill_address','bill_tel','tax_id','pre_name','mode_name','email','customer_typename','payment','credit_thb');"></textarea>
												<input type='hidden' name="h_bill_id" id="h_bill_id" readonly>
											</div>
										</div>
										<div class="cidc-row">
											<div class="cidc-label">เบอร์โทร</div>
											<div class="cidc-value">
												<input type="text" name="display_bill_tel" id="display_bill_tel" class="cidc-value-input" readonly placeholder="">
											</div>
										</div>
										<div class="cidc-row">
											<div class="cidc-label">สถานะลูกค้า</div>
											<div class="cidc-value">
												<i class="far fa-gem cidc-status-icon"></i>
												<input type="text" name="mode_name" id="mode_name" class="cidc-value-input" readonly placeholder="">
											</div>
										</div>
									</div>

									<!-- Column 2 -->
									<div class="cidc-col">
										<div class="cidc-row">
											<div class="cidc-label">ชื่อลูกค้า</div>
											<div class="cidc-value">
												<input type="text" name="display_bill_name" id="display_bill_name" class="cidc-value-input" readonly placeholder="">
											</div>
										</div>
										<div class="cidc-row">
											<div class="cidc-label">ประเภทลูกค้า</div>
											<div class="cidc-value">
												<input type="text" name="customer_typename" id="customer_typename" class="cidc-value-input" readonly placeholder="">
											</div>
										</div>
										<div class="cidc-row">
											<div class="cidc-label">เครดิตเทอม</div>
											<div class="cidc-value">
												<input type="text" name="credit_thb" id="credit_thb" class="cidc-value-input purple-text underline" readonly placeholder="">
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="so-grid-3">
						<!-- คำนำหน้าชื่อ -->
						<div class="so-field-group">
							<label class="so-label">คำนำหน้าชื่อ<span class="required">*</span></label>
							<div class="so-select-wrapper">
								<select name="pre_name" id="pre_name" class="so-select">
									<option value="">Select</option>
									<option value="นาย">นาย</option>
									<option value="นาง">นาง</option>
									<option value="นางสาว">นางสาว</option>
									<option value="บริษัท">บริษัท</option>
									<option value="หจก.">หจก.</option>
								</select>
							</div>
						</div>

						<!-- ชื่อออกบิล -->
						<div class="so-field-group">
							<label class="so-label">ชื่อออกบิล<span class="required">*</span></label>
							<div class="so-input-wrapper">
								<input type='text' name="bill_name" id="bill_name" class="so-input" placeholder="ชื่อที่ต้องการออกบิล..." style="padding-right: 32px;">
								<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('bill_name').value=''"></i>
							</div>
						</div>

						<!-- เบอร์โทรศัพท์ -->
						<div class="so-field-group">
							<label class="so-label">เบอร์โทรศัพท์<span class="required">*</span></label>
							<div class="so-input-wrapper">
								<input type='text' name="bill_tel" id="bill_tel" class="so-input" readonly placeholder="เบอร์โทรศัพท์..." style="padding-right: 32px;">
								<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('bill_tel').value=''"></i>
							</div>
						</div>
					</div>

					<!-- ที่อยู่ออกบิล -->
					<div class="so-field-group" style="margin-bottom: 24px;">
						<label class="so-label">ที่อยู่ออกบิล<span class="required">*</span></label>
						<div class="so-input-wrapper" style="width: 100%; max-width: 1032px;">
							<input type="text" name="bill_address" id="bill_address" class="so-input" style="width: 100%; max-width: 1032px; padding-right: 32px;" readonly placeholder="ที่อยู่ที่ใช้ในการออกบิล...">
							<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('bill_address').value=''"></i>
						</div>
					</div>

					<div class="so-grid-3">
						<!-- ผู้แนะนำ -->
						<div class="so-field-group">
							<label class="so-label">ผู้แนะนำ</label>
							<div class="so-input-wrapper">
								<input type="text" name="suggest" id="suggest" class="so-input" placeholder="ระบุชื่อผู้แนะนำ..." required style="padding-right: 32px;">
								<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('suggest').value=''"></i>
							</div>
						</div>

						<!-- ลูกค้าซื้อซ้ำ -->
						<div class="so-field-group" style="justify-content: flex-end;">
							<div style="display: flex; align-items: center; height: 42px;">
								<label class="so-toggle-pill" id="lbl-repeat_cus">
									<input type="checkbox" name="repeat_cus" id="repeat_cus" value="1">
									<span>ลูกค้าซื้อซ้ำ</span>
								</label>
							</div>
						</div>
					</div>
				</div>

				<div class="so-card">
					<!-- Section: การชำระเงิน -->
					<div class="so-section-title-container">
						<h2 class="so-section-title">การชำระเงิน</h2>
						<hr class="so-divider">
					</div>

					<!-- Radio Buttons Toggle for Credit vs Cash -->
					<div class="so-payment-modes">
						<label class="so-payment-radio-label" id="lbl-pay_mode_credit">
							<input type="radio" name="pay_mode" id="pay_mode_credit" value="credit" onclick="switchPaymentMode('credit')">
							<span>เครดิต</span>
						</label>
						<label class="so-payment-radio-label" id="lbl-pay_mode_cash">
							<input type="radio" name="pay_mode" id="pay_mode_cash" value="cash" onclick="switchPaymentMode('cash')">
							<span>ชำระเงินสด</span>
						</label>
					</div>

					<!-- Hidden inputs for mapping and state -->
					<input type="hidden" id="h_credit_ckk_value" value="">

					<!-- Hidden actual select dropdown which is required by the form -->
					<select name="payment" id="payment" style="display:none;" required>
						<option value="">**Please Select Item**</option>
					</select>

					<!-- Row: Credit Fields (Only shown in Credit mode) -->
					<div class="so-grid-2" id="row-credit-fields" style="display: none;">
						<!-- จำนวนเครดิต (วัน) -->
						<div class="so-field-group">
							<label class="so-label">จำนวนเครดิต (วัน)</label>
							<input type="text" id="display_credit_days" class="so-input" readonly placeholder="30">
						</div>

						<!-- ยอดเงินเครดิต -->
						<div class="so-field-group">
							<label class="so-label">ยอดเงินเครดิต</label>
							<input type="text" id="display_credit_limit" class="so-input" readonly placeholder="0.00">
						</div>
					</div>

					<!-- Row: Cash Fields (Only shown in Cash mode) -->
					<div class="so-grid-3" id="row-cash-fields" style="display: none; margin-bottom: 24px;">
						<!-- วิธีชำระเงิน -->
						<div class="so-field-group">
							<label class="so-label">วิธีชำระเงิน</label>
							<div class="so-select-wrapper">
								<select id="payment_method" name="payment_method" class="so-select">
									<option value="">เลือกวิธีชำระเงิน</option>
								</select>
							</div>
						</div>

						<!-- ช่องทางการชำระ -->
						<div class="so-field-group">
							<label class="so-label">ช่องทางการชำระ</label>
							<div class="so-select-wrapper">
								<select id="payment_cash_select" class="so-select" onchange="document.getElementById('payment').value = this.value; evaluateDebtPanel();">
									<option value="">Select</option>
								</select>
							</div>
						</div>

						<!-- หลักฐานการโอนเงิน -->
						<div class="so-field-group">
							<label class="so-label">หลักฐานการโอนเงิน</label>
							<div class="so-file-upload">
								<label for="slip_upload" class="so-input" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; color: #333333;">
									<span id="file_name_display">Choose File</span>
									<i class="far fa-image" style="color: #333333; font-size: 18px;"></i>
								</label>
								<input type="file" name="slip_upload" id="slip_upload" style="display: none;" onchange="document.getElementById('file_name_display').textContent = this.files[0] ? this.files[0].name : 'Choose File'">
							</div>
						</div>
					</div>

					<!-- รายละเอียดการชำระเงิน -->
					<div class="so-field-group" style="margin-bottom: 24px;">
						<label class="so-label">รายละเอียดการชำระเงิน</label>
						<div class="so-input-wrapper">
							<input type="text" name="payment_des" id="payment_des" class="so-input" placeholder="ระบุรายละเอียดเพิ่มเติม..." style="width: 1032px; max-width: 100%; padding-right: 32px;">
							<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('payment_des').value=''"></i>
						</div>
					</div>
				</div>


				<!-- Bottom product & delivery details section -->
				<div class="so-card" style="padding: 24px;">
					<!-- <div class="bottom-tabs-container"> -->
					<!-- <a class="bottom-tab-btn active" onclick="openCity1('pd', this)">
								รายการสินค้า
							</a> -->
					<!-- <a class="bottom-tab-btn" onclick="openCity1('cs', this)">
								รายละเอียดการจัดส่ง
							</a>
							<a class="bottom-tab-btn" onclick="openCity1('cs1', this)">
								การจัดส่ง(เพิ่มเติม)
							</a> -->
					<!-- </div> -->
					<div id="pd" class="w3-container city1">

						<?php
						if ($_SESSION["department"] == 'วิศวกรรม') {
							include('product_engineer.php');
						} else {
							include('product_salehos.php');
						}
						?>

						<?php /*include ('product_salehos.php')*/ ?>

					</div>

					<!-- วงเงิน (ซ่อน/เก็บค่าเหมือนเดิม) -->
					<input type="hidden" name="credit_thb" id="credit_thb">
					<input type="hidden" name="sum_ca" id="sum_ca">
					<input type="hidden" name="sum_amount_total" id="sum_amount_total" value="0">

					<!-- พื้นที่ตารางหนี้คงค้าง -->
					<div id="debt_panel" style="margin-top:16px; display:none;">
						<h4 class="so-section-sub-title" style="color: #612989; font-size: 15px; font-weight: 600; margin-bottom: 12px;">ยอดหนี้คงค้าง</h4>
						<div id="debt_table_wrap"></div>
						<div id="credit_summary" style="margin-top:8px; font-weight:600;"></div>
					</div>

					<!-- เอกสารแนบบิล HIDDEN -->
				</div>

				<!-- NEW DELIVERY CARD -->
				<div class="so-tabs-container" style="margin-top: 24px;">
					<button type="button" class="so-tab-btn active" onclick="openDelTab('del_info', this)">ข้อมูลการจัดส่ง</button>
					<button type="button" class="so-tab-btn" onclick="openDelTab('del_cost', this)">ค่าจัดส่ง</button>
				</div>
				<div class="so-card" style="padding: 24px;">

					<!-- TAB 1: ข้อมูลการจัดส่ง -->
					<div id="del_info" class="so-del-tab-content">
						<style>
							#del_info .so-section-title-container {
								margin-bottom: 24px;
							}

							#del_info .so-section-title {
								font-size: 18px;
								color: #3B3B3B;
								font-weight: 500;
								margin: 0 0 16px 0;
								font-family: 'Prompt', sans-serif;
							}

							#del_info .so-divider {
								border: none;
								border-top: 2px solid #EDE9F0;
								margin: 0;
							}

							#del_info .so-grid-6-col {
								display: grid;
								grid-template-columns: repeat(6, 1fr);
								gap: 20px 16px;
								margin-top: 24px;
							}

							#del_info .so-field-group {
								display: flex;
								flex-direction: column;
								gap: 8px;
								margin-top: 0 !important;
							}

							#del_info .so-label {
								font-size: 14px;
								font-weight: 500;
								color: #612989 !important;
								margin: 0;
								font-family: 'Prompt', sans-serif;
							}

							#del_info .so-select-wrapper {
								position: relative;
								display: flex;
								align-items: center;
								width: 100% !important;
							}

							#del_info .so-select,
							#del_info .so-input {
								background-color: #F4F3F7 !important;
								border: none !important;
								border-radius: 8px !important;
								font-family: 'Prompt', sans-serif !important;
								font-size: 14px !important;
								color: #333333 !important;
								width: 100% !important;
								height: 42px !important;
								padding: 0 12px !important;
								box-sizing: border-box !important;
								outline: none !important;
								transition: background-color 0.2s ease !important;
							}

							#del_info .so-select:focus,
							#del_info .so-input:focus {
								background-color: #ECEAF0 !important;
							}

							#del_info .so-select {
								padding-right: 40px !important;
								appearance: none !important;
								-webkit-appearance: none !important;
								-moz-appearance: none !important;
							}

							#del_info .so-toggle-btn {
								flex: 1;
								text-align: center;
								background-color: #F4F3F7;
								border-radius: 8px;
								padding: 12px;
								cursor: pointer;
								transition: all 0.2s ease;
								display: flex;
								align-items: center;
								justify-content: center;
								height: 42px;
								box-sizing: border-box;
							}

							#del_info .so-toggle-btn span {
								color: #6e6e6eff;
								font-size: 14px;
								font-weight: 400;
								font-family: 'Prompt', sans-serif;
								transition: color 0.2s ease;
							}

							#del_info .so-toggle-btn:has(input:checked) {
								background-color: #612989 !important;
							}

							#del_info .so-toggle-btn:has(input:checked) span {
								color: #FFFFFF !important;
							}
						</style>

						<div class="so-section-title-container">
							<h3 class="so-section-title">ข้อมูลการจัดส่ง</h3>
							<hr class="so-divider">
						</div>

						<div class="so-grid-6-col">
							<!-- วิธีการจัดส่ง -->
							<div class="so-field-group" style="grid-column: span 2;">
								<label class="so-label">วิธีการจัดส่ง<span style="color:red">*</span></label>
								<div class="so-select-wrapper">
									<select name="delivery_type" id="delivery_type" class="so-select">
										<option value="1">Sale รับเอง</option>
										<option value="2">ช่างรับเอง</option>
										<option value="3">ลูกค้ารับเอง</option>
										<option value="4">บริษัทจัดส่ง</option>
									</select>
								</div>
							</div>

							<!-- บริษัทขนส่ง -->
							<div class="so-field-group" style="grid-column: span 2;">
								<label class="so-label">บริษัทขนส่ง<span style="color:red">*</span></label>
								<div class="so-select-wrapper">
									<select name="transport_company" id="transport_company" class="so-select">
										<option value="">เลือกบริษัทขนส่ง</option>
										<option value="1">Kerry</option>
										<option value="2">Flash</option>
										<option value="3">J&T</option>
										<option value="4">ไปรษณีย์ไทย</option>
									</select>
								</div>
							</div>

							<!-- วันที่ในการจัดส่ง -->
							<div class="so-field-group" style="grid-column: span 2;">
								<label class="so-label">วันที่ในการจัดส่ง<span style="color:red">*</span></label>
								<div class="calendar-wrapper" style="width: 100%; display: flex;">
									<input name="start_date" type="date" id="start_date" class="so-input" style="padding-right: 40px;" />
								</div>
							</div>

							<!-- เลือกช่วงเวลา -->
							<div class="so-field-group" style="grid-column: span 1;">
								<label class="so-label">ช่วงเวลา</label>
								<div class="so-select-wrapper">
									<select name="time_range" id="time_range" class="so-select">
										<option value="">เลือกช่วงเวลา</option>
										<option value="morning">ช่วงเช้า</option>
										<option value="afternoon">ช่วงบ่าย</option>
										<option value="allday">ทั้งวัน</option>
										<option value="specific">กำหนดเวลา</option>
									</select>
								</div>
							</div>

							<!-- เวลาในการจัดส่ง -->
							<div class="so-field-group" style="grid-column: span 1;">
								<label class="so-label">เวลาในการจัดส่ง<span style="color:red">*</span></label>
								<div class="time-wrapper">
									<input id="start_time" name="start_time" class="so-input" type="time" style="padding-right: 40px;" />
								</div>
							</div>

							<!-- ช่วงวันที่โดยประมาณ -->
							<div class="so-field-group" style="grid-column: span 4;">
								<label class="so-label">ช่วงวันที่โดยประมาณ</label>
								<div style="position: relative; display: flex; align-items: center; width: 100%;">
									<input name="between_date" class="so-input" type="text" id="between_date" placeholder="ช่วงวันที่โดยประมาณ" style="padding-right: 36px !important;" />
									<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('between_date').value=''"></i>
								</div>
							</div>

							<!-- หมายเหตุสถานะเพิ่มเติม -->
							<div class="so-field-group" style="grid-column: span 6;">
								<label class="so-label">หมายเหตุสถานะเพิ่มเติม</label>
								<div style="position: relative; display: flex; align-items: center; width: 100%;">
									<input name="status_comment" type="text" id="status_comment" class="so-input" placeholder="หมายเหตุสถานะเพิ่มเติม" style="padding-right: 36px !important;" />
									<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('status_comment').value=''"></i>
								</div>
							</div>
						</div>

						<div style="display: flex; gap: 16px; margin-top: 24px;">
							<label class="so-toggle-btn">
								<input type="checkbox" id="call_customer" name="call_customer" value="1" style="display:none;" onchange="this.parentElement.style.backgroundColor = this.checked ? '#612989' : '#F4F3F7'; this.nextElementSibling.style.color = this.checked ? '#FFFFFF' : '#6e6e6eff';">
								<span style="color: #6e6e6eff; font-size: 14px; font-weight: 500; font-family: 'Prompt', sans-serif;">ต้องการให้โทรแจ้ง</span>
							</label>

							<label class="so-toggle-btn">
								<input type="checkbox" name="ref_12" id="ref_12" value="1" style="display:none;" onchange="this.parentElement.style.backgroundColor = this.checked ? '#612989' : '#F4F3F7'; this.nextElementSibling.style.color = this.checked ? '#FFFFFF' : '#6e6e6eff';">
								<span style="color: #6e6e6eff; font-size: 14px; font-weight: 500; font-family: 'Prompt', sans-serif;">ส่งสินค้าด้วยใบรับสินค้า (ไม่ระบุราคา)</span>
							</label>
						</div>
					</div>

					<!-- TAB 2: ค่าจัดส่ง -->
					<div id="del_cost" class="so-del-tab-content" style="display:none;">
						<h3 class="so-section-title" style="font-size: 18px; color: #3B3B3B; margin-bottom: 24px;">ค่าจัดส่ง</h3>

						<div class="so-grid-3">
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">วันที่คีย์ค่าส่ง</label>
								<div style="position: relative; display: flex; align-items: center;">
									<input name="shipping_date" type="date" id="shipping_date" class="so-input" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%; font-family: 'Prompt', sans-serif; height: 42px;" />
								</div>
							</div>

							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">รหัสอ้างอิง 1</label>
								<div style="position: relative; display: flex; align-items: center;">
									<input name="shipping_ref1" type="text" id="shipping_ref1" class="so-input" placeholder="" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%; padding-right: 32px; font-family: 'Prompt', sans-serif;" />
									<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('shipping_ref1').value=''"></i>
								</div>
							</div>

							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">รหัสอ้างอิง 2</label>
								<div style="position: relative; display: flex; align-items: center;">
									<input name="shipping_ref2" type="text" id="shipping_ref2" class="so-input" placeholder="" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%; padding-right: 32px; font-family: 'Prompt', sans-serif;" />
									<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="document.getElementById('shipping_ref2').value=''"></i>
								</div>
							</div>
						</div>

						<div class="so-grid-3" style="margin-top: 16px;">
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">ค่าจัดส่ง</label>
								<input name="shipping_cost" type="text" id="shipping_cost" class="so-input" value="0.00" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%; font-family: 'Prompt', sans-serif;" />
							</div>
						</div>
					</div>
				</div>
				<!-- NEW DELIVERY CARD END -->

				<!-- NEW ADDRESS CARD -->
				<div class="so-tabs-container" style="margin-top: 24px;">
					<button type="button" class="so-tab-btn active" onclick="openAddrTab('addr_main', this)">ที่อยู่</button>
					<button type="button" class="so-tab-btn" onclick="openAddrTab('addr_detail', this)">รายละเอียดที่อยู่</button>
					<button type="button" class="so-tab-btn" onclick="openAddrTab('addr_extra', this)">ที่อยู่เพิ่มเติม</button>
				</div>
				<div class="so-card" style="padding: 24px;">

					<!-- TAB 1: ที่อยู่ -->
					<div id="addr_main" class="so-addr-tab-content">
						<h3 class="so-section-title" style="font-size: 18px; color: #3B3B3B; margin-bottom: 24px;">ที่อยู่จัดส่ง</h3>
						<hr style="border: 0; border-top: 1px solid #EBEBEB; margin-bottom: 24px;">

						<div style="display: flex; gap: 16px; margin-bottom: 24px;">
							<button type="button" style="background-color: #F4E8FF; color: #612989; border: none; border-radius: 24px; padding: 10px 24px; font-family: 'Prompt', sans-serif; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
								<i class="fas fa-search"></i> ค้นหาที่อยู่
							</button>
							<button type="button" style="background-color: #FFFFFF; color: #612989; border: 1px solid #EBEBEB; border-radius: 24px; padding: 10px 24px; font-family: 'Prompt', sans-serif; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
								<img src="img/icons/database.png" alt="database" style="width: 16px; height: 16px;"> เพิ่มลงฐานลูกค้า
							</button>
						</div>

						<div class="so-grid-3">
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">ชื่อผู้ติดต่อ<span style="color:red">*</span></label>
								<div style="position: relative; display: flex; align-items: center;">
									<input name="contact_name" type="text" class="so-input" placeholder="ใส่ชื่อผู้ติดต่อ" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%; padding-right: 32px;" />
									<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="this.previousElementSibling.value=''"></i>
								</div>
							</div>
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">เบอร์โทร<span style="color:red">*</span></label>
								<input name="contact_tel" type="text" class="so-input" placeholder="ใส่เฉพาะตัวเลข" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%;" />
							</div>
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">จังหวัด<span style="color:red">*</span></label>
								<div class="so-select-wrapper">
									<select name="contact_province" class="so-select" style="background-color: #F4F3F7; border:none; border-radius: 8px;">
										<option value="">เลือกจังหวัด</option>
										<?php
										$strSQL_prov_main = "select * from tb_province order by province_ID ";
										$objQuery_prov_main = mysqli_query($conn, $strSQL_prov_main);
										if ($objQuery_prov_main) {
											while ($objResuut_prov_main = mysqli_fetch_array($objQuery_prov_main, MYSQLI_ASSOC)) {
										?>
												<option value="<?php echo $objResuut_prov_main['province_name']; ?>"><?php echo $objResuut_prov_main['province_name']; ?></option>
										<?php
											}
										}
										?>
									</select>
								</div>
							</div>
						</div>

						<div class="so-field-group" style="margin-top: 16px;">
							<label class="so-label" style="color: #612989;">ที่อยู่ในการส่งสินค้า<span style="color:red">*</span></label>
							<div style="position: relative; display: flex; align-items: center;">
								<input name="shipping_address" type="text" class="so-input" placeholder="ใส่ที่อยู่ส่งสินค้า" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%; padding-right: 32px;" />
								<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="this.previousElementSibling.value=''"></i>
							</div>
						</div>

						<div class="so-field-group" style="margin-top: 16px;">
							<label class="so-label" style="color: #612989;">สถานที่ติดตั้งเครื่อง<span style="color:red">*</span></label>
							<div style="position: relative; display: flex; align-items: center;">
								<input name="install_location" type="text" class="so-input" placeholder="ใส่ที่ติดตั้งเครื่อง" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%; padding-right: 32px;" />
								<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="this.previousElementSibling.value=''"></i>
							</div>
						</div>
					</div>

					<!-- TAB 2: ที่อยู่เพิ่มเติม -->
					<div id="addr_extra" class="so-addr-tab-content" style="display:none;">
						<div id="extra_address_list">
							<div class="extra-addr-row" data-index="1">
								<h3 class="so-section-title extra-addr-title" style="font-size: 18px; color: #3B3B3B; margin-bottom: 24px;">ที่อยู่เพิ่มเติม 1</h3>
								<hr style="border: 0; border-top: 1px solid #EBEBEB; margin-bottom: 24px;">

								<div class="so-grid-3">
									<div class="so-field-group">
										<label class="so-label extra-contact-name-label" style="color: #612989;">ชื่อผู้ติดต่อ (เพิ่มเติม1)</label>
										<div style="position: relative; display: flex; align-items: center;">
											<input name="extra_contact_name_1" type="text" class="so-input" placeholder="ชื่อผู้ติดต่อ" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%; padding-right: 32px;" />
											<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="this.previousElementSibling.value=''"></i>
										</div>
									</div>
									<div class="so-field-group">
										<label class="so-label extra-contact-tel-label" style="color: #612989;">เบอร์โทร (เพิ่มเติม1)</label>
										<div style="position: relative; display: flex; align-items: center;">
											<input name="extra_contact_tel_1" type="text" class="so-input" placeholder="เบอร์โทร" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%; padding-right: 32px;" />
											<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="this.previousElementSibling.value=''"></i>
										</div>
									</div>
									<div class="so-field-group">
										<label class="so-label" style="color: #612989;">จังหวัด</label>
										<div class="so-select-wrapper">
											<select name="extra_contact_province_1" class="so-select" style="background-color: #F4F3F7; border:none; border-radius: 8px;">
												<option value="">เลือกจังหวัด</option>
												<?php
												$strSQL_prov_extra = "select * from tb_province order by province_ID ";
												$objQuery_prov_extra = mysqli_query($conn, $strSQL_prov_extra);
												if ($objQuery_prov_extra) {
													while ($objResuut_prov_extra = mysqli_fetch_array($objQuery_prov_extra, MYSQLI_ASSOC)) {
												?>
														<option value="<?php echo $objResuut_prov_extra['province_name']; ?>"><?php echo $objResuut_prov_extra['province_name']; ?></option>
												<?php
													}
												}
												?>
											</select>
										</div>
									</div>
								</div>

								<div style="display: flex; gap: 16px; margin-top: 16px; align-items: flex-end; margin-bottom: 24px;">
									<div class="so-field-group" style="flex: 1;">
										<label class="so-label extra-shipping-address-label" style="color: #612989;">ที่อยู่ส่งสินค้า (เพิ่มเติม1)</label>
										<div style="position: relative; display: flex; align-items: center;">
											<input name="extra_shipping_address_1" type="text" class="so-input" placeholder="ที่อยู่ส่งสินค้า" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%; padding-right: 32px;" />
											<i class="fas fa-times" style="position: absolute; right: 12px; cursor: pointer; color: #8E8B94;" onclick="this.previousElementSibling.value=''"></i>
										</div>
									</div>
									<button type="button" onclick="removeExtraAddress(this)" style="background-color: #FFFFFF; color: #DC3545; border: 1px solid #EBEBEB; border-radius: 24px; padding: 0 24px; height: 42px; font-family: 'Prompt', sans-serif; font-weight: 500; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
										<i class="far fa-trash-alt"></i> ลบที่อยู่
									</button>
								</div>
							</div>
						</div>

						<div style="margin-top: 24px;">
							<button type="button" onclick="addExtraAddress()" style="background-color: #F4E8FF; color: #612989; border: none; border-radius: 24px; padding: 10px 24px; font-family: 'Prompt', sans-serif; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
								<img src="img/icons/add_address.png" alt="add_address" style="width: 16px; height: 16px;"> เพิ่มที่อยู่
							</button>
						</div>

						<script>
							function addExtraAddress() {
								const list = document.getElementById('extra_address_list');
								const template = list.querySelector('.extra-addr-row').cloneNode(true);

								// Clear inputs in cloned node
								template.querySelectorAll('input').forEach(input => input.value = '');
								template.querySelectorAll('select').forEach(select => select.selectedIndex = 0);

								list.appendChild(template);
								updateExtraAddressIndices();
							}

							function removeExtraAddress(btn) {
								const list = document.getElementById('extra_address_list');
								if (list.querySelectorAll('.extra-addr-row').length > 1) {
									btn.closest('.extra-addr-row').remove();
									updateExtraAddressIndices();
								} else {
									alert('ต้องมีที่อยู่เพิ่มเติมอย่างน้อย 1 รายการ หรือถ้าไม่ต้องการใช้ ให้เว้นว่างข้อมูลไว้ครับ');
								}
							}

							function updateExtraAddressIndices() {
								const rows = document.querySelectorAll('.extra-addr-row');
								rows.forEach((row, index) => {
									const displayIndex = index + 1;
									row.setAttribute('data-index', displayIndex);
									row.querySelector('.extra-addr-title').innerHTML = 'ที่อยู่เพิ่มเติม ' + displayIndex;
									row.querySelector('.extra-contact-name-label').innerHTML = 'ชื่อผู้ติดต่อ (เพิ่มเติม' + displayIndex + ')';
									row.querySelector('.extra-contact-tel-label').innerHTML = 'เบอร์โทร (เพิ่มเติม' + displayIndex + ')';
									row.querySelector('.extra-shipping-address-label').innerHTML = 'ที่อยู่ส่งสินค้า (เพิ่มเติม' + displayIndex + ')';

									// Update input names dynamically
									const contactName = row.querySelector('input[name^="extra_contact_name"]');
									if (contactName) contactName.name = 'extra_contact_name_' + displayIndex;

									const contactTel = row.querySelector('input[name^="extra_contact_tel"]');
									if (contactTel) contactTel.name = 'extra_contact_tel_' + displayIndex;

									const contactProvince = row.querySelector('select[name^="extra_contact_province"]');
									if (contactProvince) contactProvince.name = 'extra_contact_province_' + displayIndex;

									const shippingAddress = row.querySelector('input[name^="extra_shipping_address"]');
									if (shippingAddress) shippingAddress.name = 'extra_shipping_address_' + displayIndex;
								});
							}
						</script>
					</div>

					<!-- TAB 3: รายละเอียดที่อยู่ -->
					<div id="addr_detail" class="so-addr-tab-content" style="display:none;">
						<h3 class="so-section-title" style="font-size: 18px; color: #3B3B3B; margin-bottom: 24px;">รายละเอียดที่อยู่</h3>
						<hr style="border: 0; border-top: 1px solid #EBEBEB; margin-bottom: 24px;">

						<div class="so-grid-3" style="align-items: end;">
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">จอดรถหน้าบ้าน</label>
								<div style="display: flex; gap: 16px; height: 42px; align-items: center;">
									<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 14px; color: #333; font-weight: 500;">
										<input type="radio" name="park_front" value="1" checked style="accent-color: #612989; width: 18px; height: 18px;"> ได้
									</label>
									<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 14px; color: #333; font-weight: 500;">
										<input type="radio" name="park_front" value="0" style="accent-color: #612989; width: 18px; height: 18px;"> ไม่ได้
									</label>
								</div>
							</div>
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">สถานที่จอดรถ</label>
								<input name="park_location" type="text" class="so-input" placeholder="สถานที่จอดรถ" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%;" />
							</div>
							<div class="so-field-group">
								<label style="cursor: pointer; display: block;">
									<input type="checkbox" name="is_high_roof" value="1" style="display: none;" onchange="this.nextElementSibling.style.backgroundColor = this.checked ? '#612989' : '#F4F3F7'; this.nextElementSibling.style.color = this.checked ? 'white' : '#6e6e6e';">
									<div style="background-color: #F4F3F7; border-radius: 8px; padding: 10px; display: flex; align-items: center; justify-content: center; color: #6e6e6e; font-size: 14px; font-family: 'Prompt', sans-serif; height: 42px; transition: all 0.2s; user-select: none;">รถหลังคาสูงเข้าได้</div>
								</label>
							</div>
						</div>

						<div class="so-grid-3" style="margin-top: 16px;">
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">ทางเข้าบ้าน</label>
								<div style="display: flex; gap: 16px; height: 42px; align-items: center;">
									<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 14px; color: #333; font-weight: 500;">
										<input type="radio" name="entrance_type" value="1" checked style="accent-color: #612989; width: 18px; height: 18px;"> ทางราบ
									</label>
									<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 14px; color: #333; font-weight: 500;">
										<input type="radio" name="entrance_type" value="2" style="accent-color: #612989; width: 18px; height: 18px;"> บันไดก่อนเข้าบ้าน
									</label>
								</div>
							</div>
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">จำนวนขั้นบันได</label>
								<input name="stair_count" type="text" class="so-input" placeholder="ใส่เฉพาะตัวเลข" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%;" />
							</div>
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">ชั้นที่ติดตั้ง</label>
								<input name="install_floor" type="text" class="so-input" placeholder="ใส่เฉพาะตัวเลข" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%;" />
							</div>
						</div>

						<div class="so-grid-3" style="margin-top: 16px;">
							<div class="so-field-group" style="min-width: 0;">
								<label class="so-label" style="color: #612989;">ห้องที่ติดตั้ง</label>
								<div style="display: flex; gap: 16px; height: 42px; align-items: center;">
									<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 14px; color: #333; font-weight: 500;">
										<input type="radio" name="room_type" value="1" checked style="accent-color: #612989; width: 18px; height: 18px;"> ห้องโถง
									</label>
									<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 14px; color: #333; font-weight: 500;">
										<input type="radio" name="room_type" value="2" style="accent-color: #612989; width: 18px; height: 18px;"> ห้องนอน
									</label>
								</div>
							</div>
							<div class="so-field-group" style="min-width: 0;">
								<label class="so-label" style="color: #612989;">ขนาดประตูห้อง</label>
								<div style="display: flex; gap: 16px;">
									<input name="door_width" type="text" class="so-input" placeholder="ความกว้าง (ซม.)" style="background-color: #F4F3F7; border:none; border-radius: 8px; flex: 1; min-width: 0;" />
									<input name="door_height" type="text" class="so-input" placeholder="ความสูง (ซม.)" style="background-color: #F4F3F7; border:none; border-radius: 8px; flex: 1; min-width: 0;" />
								</div>
							</div>
							<div class="so-field-group" style="min-width: 0;">
								<label class="so-label" style="color: #612989;">ขนาดบันได</label>
								<div style="display: flex; gap: 16px;">
									<input name="stair_width" type="text" class="so-input" placeholder="ความกว้าง (ซม.)" style="background-color: #F4F3F7; border:none; border-radius: 8px; flex: 1; min-width: 0;" />
									<input name="stair_height" type="text" class="so-input" placeholder="ความสูง (ซม.)" style="background-color: #F4F3F7; border:none; border-radius: 8px; flex: 1; min-width: 0;" />
								</div>
							</div>
						</div>

						<div class="so-grid-3" style="margin-top: 16px;">
							<div class="so-field-group" style="min-width: 0;">
								<label class="so-label" style="color: #612989;">ประตูลิฟต์</label>
								<div style="display: flex; gap: 16px;">
									<input name="elev_door_width" type="text" class="so-input" placeholder="ความกว้าง (ซม.)" style="background-color: #F4F3F7; border:none; border-radius: 8px; flex: 1; min-width: 0;" />
									<input name="elev_door_height" type="text" class="so-input" placeholder="ความสูง (ซม.)" style="background-color: #F4F3F7; border:none; border-radius: 8px; flex: 1; min-width: 0;" />
								</div>
							</div>
							<div class="so-field-group" style="grid-column: span 1; min-width: 0;">
								<label class="so-label" style="color: #612989;">ขนาดห้องลิฟต์</label>
								<div style="display: flex; gap: 16px;">
									<input name="elev_width" type="text" class="so-input" placeholder="ความกว้าง (ซม.)" style="background-color: #F4F3F7; border:none; border-radius: 8px; min-width: 0; flex: 1;" />
									<input name="elev_height" type="text" class="so-input" placeholder="ความสูง (ซม.)" style="background-color: #F4F3F7; border:none; border-radius: 8px; min-width: 0; flex: 1;" />
									<input name="elev_depth" type="text" class="so-input" placeholder="ความลึก (ซม.)" style="background-color: #F4F3F7; border:none; border-radius: 8px; min-width: 0; flex: 1;" />
								</div>
							</div>
							<div class="so-field-group" style="min-width: 0;">
								<label class="so-label" style="color: #612989;">ขนาดบรรทุกของลิฟต์</label>
								<input name="elev_capacity" type="text" class="so-input" placeholder="น้ำหนัก (กก.)" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%;" />
							</div>
						</div>

						<div class="so-grid-3" style="margin-top: 16px;">
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">การย้ายเฟอร์นิเจอร์</label>
								<div style="display: flex; gap: 16px; height: 42px; align-items: center;">
									<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 14px; color: #333; font-weight: 500;">
										<input type="radio" name="move_furn" value="0" checked style="accent-color: #612989; width: 18px; height: 18px;"> ไม่
									</label>
									<label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 14px; color: #333; font-weight: 500;">
										<input type="radio" name="move_furn" value="1" style="accent-color: #612989; width: 18px; height: 18px;"> ย้าย
									</label>
								</div>
							</div>
							<div class="so-field-group">
								<label class="so-label" style="color: #612989;">จำนวนชิ้นที่ย้าย</label>
								<input name="move_furn_count" type="text" class="so-input" placeholder="ใส่เฉพาะตัวเลข" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%;" />
							</div>
							<div class="so-field-group" style="grid-column: span 1;">
								<label class="so-label" style="color: #612989;">รายละเอียดเฟอร์นิเจอร์</label>
								<input name="move_furn_detail" type="text" class="so-input" placeholder="รายละเอียดเฟอร์นิเจอร์" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%;" />
							</div>
						</div>

						<div class="so-field-group" style="margin-top: 16px;">
							<label class="so-label" style="color: #612989;">หมายเหตุเพิ่มเติม</label>
							<input name="addr_note" type="text" class="so-input" placeholder="lorem ipsum" style="background-color: #F4F3F7; border:none; border-radius: 8px; width: 100%;" />
						</div>
					</div>
				</div>

				<script>
					function openAddrTab(tabId, element) {
						var contents = document.getElementsByClassName('so-addr-tab-content');
						for (var i = 0; i < contents.length; i++) {
							contents[i].style.display = 'none';
						}

						var container = element.parentElement;
						var btns = container.getElementsByClassName('so-tab-btn');
						for (var i = 0; i < btns.length; i++) {
							btns[i].classList.remove('active');
						}

						document.getElementById(tabId).style.display = 'block';
						element.classList.add('active');
					}
				</script>



				<!-- NEW 3 TABS CARD -->
				<div class="so-tabs-container" style="margin-top: 24px;">
					<button type="button" class="so-tab-btn active" onclick="open3Tab('tab_doc_extra', this)"><span style="color: #E81A70; margin-right: 6px;">●</span>เอกสารเพิ่มเติม</button>
					<button type="button" class="so-tab-btn" onclick="open3Tab('tab_dept_comment', this)">ข้อความแจ้งแผนก</button>
					<button type="button" class="so-tab-btn" onclick="open3Tab('tab_attach_file', this)">แนบไฟล์</button>
				</div>
				<div class="so-card" style="padding: 24px;">

					<!-- TAB 1: เอกสารเพิ่มเติม -->
					<div id="tab_doc_extra" class="so-3tab-content">
						<h3 style="font-size: 18px; color: #3B3B3B; margin-bottom: 24px;">เอกสารเพิ่มเติม</h3>
						<hr style="border: 0; border-top: 1px solid #EBEBEB; margin-bottom: 24px;">

						<div class="so-doc-grid">
							<label class="so-doc-pill">
								<input type="checkbox" name="ref_3" value="1">
								<span>ใบ อย.</span>
							</label>
							<label class="so-doc-pill">
								<input type="checkbox" name="ref_6" value="1">
								<span>ใบนำเข้าสินค้า</span>
							</label>
							<label class="so-doc-pill">
								<input type="checkbox" name="ref_8" value="1">
								<span>ใบ PM</span>
							</label>
							<label class="so-doc-pill">
								<input type="checkbox" name="ref_9" value="1">
								<span>ใบ CAL</span>
							</label>
							<label class="so-doc-pill">
								<input type="checkbox" name="ref_11" value="1">
								<span>ใบประเมินสินค้า</span>
							</label>
							<label class="so-doc-pill">
								<input type="checkbox" name="ref_5" value="1">
								<span>ใบช่างอบรม</span>
							</label>
							<label class="so-doc-pill" style="grid-column: span 2;">
								<input type="checkbox" name="ref_2" value="1">
								<span>เอกสารตามไฟล์แนบ</span>
							</label>
							<label class="so-doc-pill" style="grid-column: span 2;">
								<input type="checkbox" name="ref_1" value="1">
								<span>เอกสาร N-Health</span>
							</label>
							<label class="so-doc-pill" style="grid-column: span 2;">
								<input type="checkbox" name="ref_4" value="1">
								<span>ใบตัวแทนจำหน่าย</span>
							</label>
							<label class="so-doc-pill" style="grid-column: span 2;">
								<input type="checkbox" name="ref_7" value="1">
								<span>ใบ CER เครื่องมือที่ใช้ทดสอบ</span>
							</label>
							<div class="so-doc-other-wrapper" style="grid-column: span 4; display: flex; flex-direction: column; justify-content: flex-end;">
								<label style="color: #612989; font-weight: 400; font-size: 14px; margin-bottom: 8px; display: block; font-family: 'Prompt', sans-serif;">อื่นๆ</label>
								<input type="text" name="ref_des" class="so-input" placeholder="ระบุรายละเอียดอื่นๆ..." style="width: 100%;" oninput="document.getElementById('ref_10_hidden').checked = (this.value.trim() !== '');">
								<input type="checkbox" name="ref_10" id="ref_10_hidden" value="1" style="display:none;">
								<!-- Hidden inputs to keep old compatibility if needed -->
								<input type="checkbox" name="ref_13" value="1" style="display:none;">
								<input type="checkbox" name="old_ref_12" value="1" style="display:none;">
							</div>
						</div>
					</div>

					<!-- TAB 2: ข้อความแจ้งแผนก -->
					<div id="tab_dept_comment" class="so-3tab-content" style="display:none;">
						<h3 style="font-size: 18px; color: #3B3B3B; margin-bottom: 24px;">ข้อความแจ้งแผนกที่เกี่ยวข้อง</h3>
						<hr style="border: 0; border-top: 1px solid #EBEBEB; margin-bottom: 24px;">

						<div style="display: flex; gap: 16px; align-items: center; margin-bottom: 24px;">
							<button type="button" onclick="addDeptComment()" style="background-color: #EFEBFF; color: #612989; border: none; border-radius: 24px; padding: 10px 24px; font-family: 'Prompt', sans-serif; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px;">
								<img src="img/icons/add_message.png" alt="add_message" style="width: 16px; height: 16px;"> เพิ่มข้อความ
							</button>
							<button type="button" onclick="this.classList.toggle('btn-dept-active');" style="background-color: #F4F3F7; border: none; padding: 10px 24px; border-radius: 8px; color: #333; font-size: 16px; font-family: 'Prompt', sans-serif; cursor: pointer; transition: background-color 0.2s, color 0.2s; width: 328px;">
								ต้องการช่างไปตรวจรับ
							</button>
						</div>

						<div id="dept_comment_list">
							<!-- Dynamic comments will go here -->
						</div>

						<!-- Hidden textareas to submit to backend -->
						<textarea name="comment_cs" id="hidden_comment_cs" style="display:none;"></textarea>
						<textarea name="comment_en" id="hidden_comment_en" style="display:none;"></textarea>
						<textarea name="comment_st" id="hidden_comment_st" style="display:none;"></textarea>
						<textarea name="comment_ad" id="hidden_comment_ad" style="display:none;"></textarea>
					</div>

					<!-- TAB 3: แนบไฟล์ -->
					<div id="tab_attach_file" class="so-3tab-content" style="display:none;">
						<h3 style="font-size: 18px; color: #3B3B3B; margin-bottom: 24px;">แนบไฟล์เพิ่มเติม</h3>
						<hr style="border: 0; border-top: 1px solid #EBEBEB; margin-bottom: 24px;">

						<button type="button" onclick="triggerAttachFile()" style="background-color: #EFEBFF; color: #612989; border: none; border-radius: 24px; padding: 10px 24px; font-family: 'Prompt', sans-serif; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; margin-bottom: 24px;">
							<img src="img/icons/import_file.png" alt="import_file" style="width: 16px; height: 16px;"> เพิ่มไฟล์
						</button>

						<div id="attach_file_list" style="display: flex; flex-wrap: wrap; gap: 16px;">
							<!-- Dynamic files will go here -->
						</div>

						<!-- Hidden file inputs to submit to backend -->
						<input type="file" name="slip1" id="hidden_slip1" style="display:none;" onchange="handleFileSelect(this, 1)">
						<input type="file" name="slip2" id="hidden_slip2" style="display:none;" onchange="handleFileSelect(this, 2)">
						<input type="file" name="slip3" id="hidden_slip3" style="display:none;" onchange="handleFileSelect(this, 3)">
						<input type="file" name="slip4" id="hidden_slip4" style="display:none;" onchange="handleFileSelect(this, 4)">
						<input type="file" name="slip5" id="hidden_slip5" style="display:none;" onchange="handleFileSelect(this, 5)">
					</div>
				</div>

				<style>
					.btn-dept-active {
						background-color: #612989 !important;
						color: #FFFFFF !important;
					}

					.so-doc-pill {
						background-color: #F4F3F7;
						border: 1px solid transparent;
						border-radius: 8px;
						padding: 12px;
						display: flex;
						align-items: center;
						justify-content: center;
						cursor: pointer;
						transition: all 0.2s ease;
						text-align: center;
						color: #8E8B94;
						font-weight: 500;
					}

					.so-doc-pill:has(input:checked) {
						background-color: #EFEBFF;
						border-color: #612989;
						color: #612989;
						font-weight: 600;
					}

					.so-doc-pill input[type="checkbox"] {
						display: none;
					}

					.dept-row {
						display: flex;
						gap: 16px;
						margin-bottom: 16px;
						align-items: flex-end;
					}
				</style>

				<script>
					function open3Tab(tabId, element) {
						var contents = document.getElementsByClassName('so-3tab-content');
						for (var i = 0; i < contents.length; i++) {
							contents[i].style.display = 'none';
						}

						var container = element.parentElement;
						var btns = container.getElementsByClassName('so-tab-btn');
						for (var i = 0; i < btns.length; i++) {
							btns[i].classList.remove('active');
							var span = btns[i].querySelector('span');
							if (span) span.style.display = 'none';
						}

						document.getElementById(tabId).style.display = 'block';

						element.classList.add('active');
						var span = element.querySelector('span');
						if (span) span.style.display = 'inline';
					}

					// === Department Comments JS ===
					let commentIdCounter = 0;

					function addDeptComment(defaultDept = '', defaultText = '') {
						const list = document.getElementById('dept_comment_list');
						const rowId = 'dept_row_' + commentIdCounter++;

						const row = document.createElement('div');
						row.className = 'dept-row';
						row.id = rowId;

						row.innerHTML = `
        <div style="flex: 0 0 200px;">
            <label style="color: #612989; font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">แผนก</label>
            <div class="so-select-wrapper">
                <select class="so-select" onchange="syncDeptComments()">
                    <option value="">เลือกแผนก</option>
                    <option value="cs" ${defaultDept === 'cs' ? 'selected' : ''}>จัดส่ง</option>
                    <option value="en" ${defaultDept === 'en' ? 'selected' : ''}>ช่าง</option>
                    <option value="st" ${defaultDept === 'st' ? 'selected' : ''}>คลังสินค้า</option>
                    <option value="ad" ${defaultDept === 'ad' ? 'selected' : ''}>Admin</option>
                </select>
            </div>
        </div>
        <div style="flex: 1; position: relative;">
            <label style="color: #612989; font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">ข้อความ</label>
            <div style="position: relative; display: flex; align-items: center;">
                <input type="text" class="so-input dept-text-input" placeholder="กรอกข้อความสำหรับแจ้งแผนก..." style="width: 100%; padding-right: 60px;" value="${defaultText}" oninput="syncDeptComments(); this.nextElementSibling.style.display = this.value ? 'block' : 'none';">
                <i class="fas fa-times" style="position: absolute; right: 40px; cursor: pointer; color: #8E8B94; display: ${defaultText ? 'block' : 'none'};" onclick="this.previousElementSibling.value=''; syncDeptComments(); this.style.display='none';"></i>
                <i class="far fa-trash-alt" style="position: absolute; right: 16px; color: #DC3545; cursor: pointer;" onclick="document.getElementById('${rowId}').remove(); syncDeptComments();"></i>
            </div>
        </div>
    `;
						list.appendChild(row);
						syncDeptComments();
					}

					function syncDeptComments() {
						let cs = '',
							en = '',
							st = '',
							ad = '';

						const rows = document.querySelectorAll('.dept-row');
						rows.forEach(row => {
							const dept = row.querySelector('select').value;
							const text = row.querySelector('.dept-text-input').value;

							if (text.trim() !== '') {
								if (dept === 'cs') cs += (cs ? '\n' : '') + text;
								if (dept === 'en') en += (en ? '\n' : '') + text;
								if (dept === 'st') st += (st ? '\n' : '') + text;
								if (dept === 'ad') ad += (ad ? '\n' : '') + text;
							}
						});

						document.getElementById('hidden_comment_cs').value = cs;
						document.getElementById('hidden_comment_en').value = en;
						document.getElementById('hidden_comment_st').value = st;
						document.getElementById('hidden_comment_ad').value = ad;
					}

					// Add an initial row
					document.addEventListener('DOMContentLoaded', function() {
						addDeptComment();
					});

					// === Attach Files JS ===
					function triggerAttachFile() {
						for (let i = 1; i <= 5; i++) {
							const input = document.getElementById('hidden_slip' + i);
							if (!input.value) {
								input.click();
								return;
							}
						}
						alert('สามารถแนบไฟล์ได้สูงสุด 5 ไฟล์ครับ');
					}

					function handleFileSelect(input, index) {
						if (input.files && input.files[0]) {
							renderFileList();
						}
					}

					function renderFileList() {
						const list = document.getElementById('attach_file_list');
						list.innerHTML = '';

						for (let i = 1; i <= 5; i++) {
							const input = document.getElementById('hidden_slip' + i);
							if (input.files && input.files[0]) {
								const fileName = input.files[0].name;

								const fileBox = document.createElement('div');
								fileBox.style.cssText = 'background-color: #FFFFFF; border: 1px solid #EBEBEB; border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; width: 300px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);';

								fileBox.innerHTML = `
                <div style="display: flex; flex-direction: column; overflow: hidden;">
                    <span style="font-size: 12px; color: #612989; font-weight: 600;">ไฟล์</span>
                    <a href="#" style="color: #612989; text-decoration: underline; text-overflow: ellipsis; white-space: nowrap; overflow: hidden; font-size: 14px;">${fileName}</a>
                </div>
                <i class="far fa-trash-alt" style="color: #DC3545; cursor: pointer; font-size: 16px; margin-left: 12px;" onclick="removeFile(${i})"></i>
            `;
								list.appendChild(fileBox);
							}
						}
					}

					function removeFile(index) {
						const input = document.getElementById('hidden_slip' + index);
						input.value = ''; // Clear file
						renderFileList();
					}
				</script>
				<!-- NEW 3 TABS CARD END -->


				<!-- Section: ข้อมูลเพิ่มเติม (ถูกซ่อนไว้) -->
				<div id="hidden-extra-info" style="display: none;">
					<div class="so-section-title-container">
						<h2 class="so-section-title">ข้อมูลเพิ่มเติม</h2>
						<hr class="so-divider">
					</div>

					<div class="so-grid-3">
						<!-- Sale -->
						<div class="so-field-group">
							<label class="so-label">Sale</label>
							<div class="so-select-wrapper">
								<?php if ($_SESSION['code'] == 'SS1') { ?>
									<select name="sale_code" id="sale_code" class="so-select">
										<option value="">**Please Select**</option>
										<?php
										$strSQL5 = "SELECT * FROM tb_team_ss1 ORDER BY sale_code ASC";
										$objQuery5 = mysqli_query($com, $strSQL5);
										while ($objResuut5 = mysqli_fetch_array($objQuery5)) {
										?>
											<option value="<?php echo $objResuut5["sale_code"]; ?>"><?php echo $objResuut5["sale_code"]; ?> - <?php echo $objResuut5["sale_name"]; ?></option>
										<?php } ?>
									</select>
								<?php } else if ($_SESSION['code'] == 'SS2') { ?>
									<select name="sale_code" id="sale_code" class="so-select">
										<option value="">**Please Select**</option>
										<?php
										$strSQL5 = "SELECT * FROM tb_team_ss2 ORDER BY sale_code ASC";
										$objQuery5 = mysqli_query($com, $strSQL5);
										while ($objResuut5 = mysqli_fetch_array($objQuery5)) {
										?>
											<option value="<?php echo $objResuut5["sale_code"]; ?>"><?php echo $objResuut5["sale_code"]; ?> - <?php echo $objResuut5["sale_name"]; ?></option>
										<?php } ?>
									</select>
								<?php } else if ($_SESSION['code'] == 'SS3') { ?>
									<select name="sale_code" id="sale_code" class="so-select">
										<option value="">**Please Select**</option>
										<?php
										$strSQL5 = "SELECT * FROM tb_team_ss3 where ckk_1='0' ORDER BY sale_code ASC";
										$objQuery5 = mysqli_query($com, $strSQL5);
										while ($objResuut5 = mysqli_fetch_array($objQuery5)) {
										?>
											<option value="<?php echo $objResuut5["sale_code"]; ?>"><?php echo $objResuut5["sale_code"]; ?> - <?php echo $objResuut5["sale_name"]; ?></option>
										<?php } ?>
									</select>
								<?php } else if ($_SESSION['code'] == 'SS5') { ?>
									<select name="sale_code" id="sale_code" class="so-select">
										<option value="">**Please Select**</option>
										<?php
										$strSQL5 = "SELECT * FROM tb_team_ss3 where sale_code IN ('S31','S32') ORDER BY sale_code ASC";
										$objQuery5 = mysqli_query($com, $strSQL5);
										while ($objResuut5 = mysqli_fetch_array($objQuery5)) {
										?>
											<option value="<?php echo $objResuut5["sale_code"]; ?>"><?php echo $objResuut5["sale_code"]; ?> - <?php echo $objResuut5["sale_name"]; ?></option>
										<?php } ?>
									</select>
								<?php } else if ($_SESSION['code'] == 'SUP_MK') { ?>
									<select name="sale_code" id="sale_code" class="so-select">
										<option value="">**Please Select**</option>
										<?php
										$strSQL5 = "SELECT * FROM tb_team_adm where ckk='1' ORDER BY sale_code ASC";
										$objQuery5 = mysqli_query($com, $strSQL5);
										while ($objResuut5 = mysqli_fetch_array($objQuery5)) {
										?>
											<option value="<?php echo $objResuut5["sale_code"]; ?>"><?php echo $objResuut5["sale_code"]; ?> - <?php echo $objResuut5["sale_name"]; ?></option>
										<?php } ?>
									</select>
								<?php } else if ($_SESSION['code'] == 'SUP_EN') { ?>
									<select name="sale_code" id="sale_code" class="so-select">
										<option value="">**Please Select**</option>
										<?php
										$strSQL5 = "SELECT * FROM tb_team_en ORDER BY sale_code ASC";
										$objQuery5 = mysqli_query($com, $strSQL5);
										while ($objResuut5 = mysqli_fetch_array($objQuery5)) {
										?>
											<option value="<?php echo $objResuut5["sale_code"]; ?>"><?php echo $objResuut5["sale_code"]; ?> - <?php echo $objResuut5["sale_name"]; ?></option>
										<?php } ?>
									</select>
								<?php } else { ?>
									<select name="sale_code" id="sale_code" class="so-select">
										<option value="">**Please Select**</option>
										<?php
										$strSQL5 = "SELECT * FROM tb_team_adm where ckk = '0' ORDER BY sale_code ASC";
										$objQuery5 = mysqli_query($com, $strSQL5);
										while ($objResuut5 = mysqli_fetch_array($objQuery5)) {
										?>
											<option value="<?php echo $objResuut5["sale_code"]; ?>"><?php echo $objResuut5["sale_code"]; ?> - <?php echo $objResuut5["sale_name"]; ?></option>
										<?php } ?>
									</select>
								<?php } ?>
							</div>
						</div>

						<!-- แนบใบเสนอราคา -->
						<div class="so-field-group">
							<label class="so-label">แนบใบเสนอราคา</label>
							<div class="so-input-with-checkbox">
								<label class="so-checkbox-label">
									<input name="with_pr" type="checkbox" value="1"> แนบใบเสนอราคา
								</label>
								<input name="pr_no" class="so-input" placeholder="เลขที่ใบเสนอราคา..." style="flex: 1;">
							</div>
						</div>

						<!-- ต้องการ SN -->
						<div class="so-field-group">
							<label class="so-label">ต้องการ SN</label>
							<div class="so-input-with-checkbox">
								<label class="so-checkbox-label">
									<input type="checkbox" name="sn_ckk" value="1"> ต้องการ SN
								</label>
								<input name="sn_no" class="so-input" placeholder="เลขที่ SN..." style="flex: 1;">
							</div>
						</div>
					</div>

					<div class="so-grid-2">
						<!-- รูปแบบการพิมพ์ -->
						<div class="so-field-group">
							<label class="so-label">รูปแบบการพิมพ์</label>
							<div class="so-radio-group-vertical">
								<label class="so-radio-label">
									<input type="radio" name="type_type" checked="checked" value="1" onclick="javascript:ckk_1();" id="object6">
									<span>พิมพ์ตามคอม</span>
								</label>
								<label class="so-radio-label">
									<input type="radio" name="type_type" value="2" onclick="javascript:ckk_1();" id="object7">
									<span>พิมพ์ตามใบสั่งซื้อ</span>
								</label>
								<label class="so-radio-label">
									<input type="radio" name="type_type" value="3" onclick="javascript:ckk_1();" id="object5">
									<span>พิมพ์ตามที่เขียน</span>
								</label>
							</div>
							<div id="dt5" style="display:none; margin-top: 10px;">
								<textarea name="type_detail" class="so-textarea" rows="2" placeholder="ระบุรายละเอียดเพิ่มเติม..."></textarea>
							</div>
						</div>

						<!-- แนบไฟล์ HIDDEN -->
						<div class="so-file-input-wrapper"><input name="slip2" type="file" class="so-file-input"></div>
						<div class="so-file-input-wrapper"><input name="slip3" type="file" class="so-file-input"></div>
						<div class="so-file-input-wrapper"><input name="slip4" type="file" class="so-file-input"></div>
						<div class="so-file-input-wrapper"><input name="slip5" type="file" class="so-file-input"></div>
					</div>
				</div> <!-- End hidden-extra-info -->
			</div>
		</div>
		</div><!-- End Card Container -->

		<script>
			function openDelTab(tabId, element) {
				var contents = document.getElementsByClassName('so-del-tab-content');
				for (var i = 0; i < contents.length; i++) {
					contents[i].style.display = 'none';
				}

				var container = element.parentElement;
				var btns = container.getElementsByClassName('so-tab-btn');
				for (var i = 0; i < btns.length; i++) {
					btns[i].classList.remove('active');
				}

				document.getElementById(tabId).style.display = 'block';
				element.classList.add('active');
			}
		</script>


		<div class="so-card" style="padding: 24px; margin-top: 24px; display:none;">
			<h3 style="font-size: 18px; color: #3B3B3B; margin-bottom: 24px;">ข้อมูลจัดส่งอื่นๆ (ระบบเดิม)</h3>
			<div id="cs" class="w3-container city1">

				<div class="so-field-group" style="margin-bottom: 24px;">
					<label class="so-label">ประเภทการจัดส่ง</label>
					<div class="so-payment-modes">
						<label class="so-payment-radio-label">
							<input type="radio" name="old_delivery_type" value="1" checked='checked' onclick="javascript:ckk_2();" id="object8">
							Sale รับเอง
						</label>
						<label class="so-payment-radio-label">
							<input type="radio" name="old_delivery_type" value="2" onclick="javascript:ckk_2();" id="object9">
							ช่างรับเอง
						</label>
						<label class="so-payment-radio-label">
							<input type="radio" name="old_delivery_type" value="3" onclick="javascript:ckk_2();" id="object10">
							ลูกค้ารับเอง
						</label>
						<label class="so-payment-radio-label">
							<input type="radio" name="old_delivery_type" value="4" onclick="javascript:ckk_2();" id="object11">
							บริษัทจัดส่ง
						</label>
					</div>
				</div>

				<!-- Grid: Dates and Times -->
				<div class="so-grid-3">
					<div class="so-field-group">
						<label class="so-label">วันที่ รับ-ส่ง</label>
						<div class="calendar-wrapper">
							<input name="old_start_date" type='date' id="start_date" class="so-input" />
						</div>
					</div>

					<div class="so-field-group">
						<label class="so-label">วันที่ต้องการโดยประมาณ</label>
						<input name="old_between_date" class="so-input" type='text' id="between_date" placeholder="ระบุวันที่โดยประมาณ..." />
					</div>

					<div class="so-field-group">
						<label class="so-label">เวลาจัดส่ง (ช่วงเวลา)</label>
						<div style="display: flex; gap: 8px; align-items: center;">
							<input id="start_time" name="old_start_time" class="so-input" type="text" placeholder="เริ่ม" style="text-align: center;" />
							<span style="color: #8E8B94;">ถึง</span>
							<input id="end_time" name="old_end_time" class="so-input" type="text" placeholder="สิ้นสุด" style="text-align: center;" />
						</div>
					</div>
				</div>

				<!-- Grid: Status -->
				<div class="so-grid-2">
					<div class="so-field-group">
						<label class="so-label">สถานะการทำงาน</label>
						<div class="so-payment-modes" style="margin-bottom: 0; height: 48px; align-items: center;">
							<label class="so-payment-radio-label">
								<input type='radio' name='status' value='ส่ง' checked='checked' />
								ส่ง
							</label>
							<label class="so-payment-radio-label">
								<input type='radio' name='status' value='รับ' />
								รับ
							</label>
						</div>
					</div>

					<div class="so-field-group">
						<label class="so-label">สถานะการส่งสินค้า (ความเห็น)</label>
						<input name="old_status_comment" type='text' id="status_comment" class="so-input" placeholder="ความเห็นสถานะ..." />
					</div>
				</div>

				<!-- Section: เงื่อนไขการส่งและข้อกำหนด (Pills) -->
				<div class="so-section-title-container" style="margin-top: 16px;">
					<h3 class="so-section-title" style="font-size: 15px; color: #612989;">เงื่อนไขการส่งและข้อกำหนด</h3>
					<hr class="so-divider">
				</div>

				<div class="so-checkbox-grid" style="margin-bottom: 24px;">
					<label class="so-checkbox-pill">
						<input type="checkbox" name="mk_research" id="mk_research" value="1">
						cs ทำแบบสอบถาม
					</label>

					<label class="so-checkbox-pill">
						<input type="checkbox" name="fix_datetime" id="fix_datetime" value="1">
						นัดวันและเวลาเรียบร้อยแล้ว
					</label>

					<label class="so-checkbox-pill">
						<input type="checkbox" id="on_time" name="on_time" value="1">
						งานสำคัญต้องตรงเวลา
					</label>

					<label class="so-checkbox-pill">
						<input type="checkbox" id="call_customer" name="old_call_customer" value="1">
						โทรแจ้งลูกค้าก่อนไป
					</label>

					<label class="so-checkbox-pill">
						<input type="checkbox" id="call_back" name="call_back" value="1">
						ต้องการให้โทรกลับเมื่อส่งเสร็จ
					</label>

					<label class="so-checkbox-pill">
						<input type="checkbox" id="no_money" name="no_money" value="1">
						ไม่ต้องเก็บเงิน
					</label>

					<label class="so-checkbox-pill">
						<input type="checkbox" name="want_bus" value="1">
						ต้องการรถใหญ่
					</label>
				</div>

				<!-- Section: การเก็บเงินปลายทาง -->
				<div class="so-section-title-container">
					<h3 class="so-section-title" style="font-size: 15px; color: #612989;">การเก็บเงินปลายทาง / โอนเงินหน้างาน</h3>
					<hr class="so-divider">
				</div>

				<div class="so-grid-3">
					<div class="so-field-group">
						<label class="so-checkbox-label">
							<input type="checkbox" name="cash" id="cash" value="1">
							เก็บเงินสด (บาท)
						</label>
						<input name="unit_cash" type='text' class="so-input" id="unit_cash" style="text-align:right" OnChange="JavaScript:chkNum(this)" placeholder="0.00">
					</div>

					<div class="so-field-group">
						<label class="so-checkbox-label">
							<input type="checkbox" name="check_paper" id="check_paper" value="1">
							รับเช็ค (บาท)
						</label>
						<input name="unit_check" type='text' class="so-input" id="unit_check" style="text-align:right" OnChange="JavaScript:chkNum(this)" placeholder="0.00" />
					</div>

					<div class="so-field-group">
						<label class="so-checkbox-label">
							<input type="checkbox" id="credit_card" name="credit_card" value="1">
							รูดการ์ด (บาท)
						</label>
						<input name="unit_credit" type='text' class="so-input" id="unit_credit" style="text-align:right" OnChange="JavaScript:chkNum(this)" placeholder="0.00" />
					</div>
				</div>

				<div class="so-grid-3" style="margin-bottom: 24px;">
					<div class="so-field-group">
						<label class="so-checkbox-label">
							<input type="checkbox" id="bill" name="bill" value="1">
							วางบิล (บาท)
						</label>
						<input name="unit_bill" type='text' class="so-input" style="text-align:right" id="unit_bill" OnChange="JavaScript:chkNum(this)" placeholder="0.00" />
					</div>

					<div class="so-field-group">
						<label class="so-checkbox-label">
							<input type="checkbox" name="tran" id="tran" value="1">
							ลูกค้าโอนเงินหน้างาน (บาท)
						</label>
						<input name="unit_tran" type='text' class="so-input" id="unit_tran" style="text-align:right" OnChange="JavaScript:chkNum(this)" placeholder="0.00">
					</div>

					<div class="so-field-group">
						<label class="so-checkbox-label">
							<input type="checkbox" id="dep" name="dep" value="1">
							อื่นๆ
						</label>
						<input name="dept" type='text' class="so-input" id="dept" placeholder="ระบุรายละเอียด..." />
					</div>
				</div>

				<!-- Section: ข้อมูลพนักงานและหน่วยงาน -->
				<div class="so-section-title-container">
					<h3 class="so-section-title" style="font-size: 15px; color: #612989;">ข้อมูลฝ่ายงานและผู้ส่งงาน</h3>
					<hr class="so-divider">
				</div>

				<div class="so-grid-3">
					<div class="so-field-group">
						<label class="so-label">แผนก - ฝ่าย</label>
						<input name="department_show" value="ฝ่ายขาย" class="so-input" type='text' id="department_show">
					</div>

					<div class="so-field-group">
						<label class="so-label">ประเภทงาน</label>
						<input name="department_name" value="Sale" class="so-input" type='text' id="department_name">
					</div>

					<div class="so-field-group">
						<label class="so-label">ประเภทลูกค้า</label>
						<input name="customer_typename" class="so-input" type='text' id="customer_typename" placeholder="ประเภทลูกค้า...">
					</div>
				</div>

				<div class="so-grid-2" style="margin-bottom: 24px;">
					<div class="so-field-group">
						<label class="so-label">ชื่อพนักงาน</label>
						<input name="employee_name" class="so-input" type='text' id="employee_name" placeholder="ค้นหาชื่อพนักงาน..." />
						<input name="h_employee_name" type="hidden" id="h_employee_name" value="" />
						<input name="add_by" value="<?php echo $_SESSION['name']; ?>&nbsp;<?php echo $_SESSION['surname']; ?>" type='hidden'>
					</div>

					<div class="so-field-group">
						<label class="so-label">เบอร์โทรพนักงาน</label>
						<input name="employee_tel" class="so-input" type='text' id="employee_tel" placeholder="เบอร์โทรศัพท์พนักงาน...">
					</div>
				</div>

				<!-- Section: ที่อยู่และผู้ติดต่อปลายทาง -->
				<div class="so-section-title-container">
					<h3 class="so-section-title" style="font-size: 15px; color: #612989;">ที่อยู่จัดส่งและผู้ติดต่อ</h3>
					<hr class="so-divider">
				</div>

				<div class="so-grid-3">
					<div class="so-field-group">
						<label class="so-label">จังหวัดจัดส่ง</label>
						<div class="so-select-wrapper">
							<select name="province_name" id="province_name" class="so-select">
								<option value="">** กรุณาเลือกจังหวัด **</option>
								<?php
								$strSQL5 = "select * from tb_province order by province_ID ";
								$objQuery5 = mysqli_query($conn, $strSQL5);
								if (!$objQuery5) {
									echo "Failed to fetch to MySQL: " . mysqli_error();
								}
								while ($objResuut5 = mysqli_fetch_array($objQuery5, MYSQLI_ASSOC)) {
								?>
									<option value="<?php echo $objResuut5['province_name']; ?>"><?php echo $objResuut5['province_name']; ?></option>
								<?php } ?>
							</select>
						</div>
					</div>

					<div class="so-field-group">
						<label class="so-label">ชื่อผู้ติดต่อ</label>
						<input name="customer_name" class="so-input" type='text' id="customer_name" placeholder="ชื่อผู้รับสาย / ผู้ติดต่อ...">
					</div>

					<div class="so-field-group">
						<label class="so-label">เบอร์โทรติดต่อลูกค้า</label>
						<input name="customer_tel" class="so-input" type='text' id="customer_tel" placeholder="เบอร์โทรศัพท์ติดต่อ...">
					</div>
				</div>

				<!-- Textareas Grid -->
				<div class="so-grid-3">
					<div class="so-field-group">
						<label class="so-label">สถานที่ส่งสินค้า (ชื่อสถานที่/จุดสังเกต)</label>
						<textarea class="so-textarea" name="address_1" rows="3" placeholder="ระบุสถานที่ส่งสินค้า..."></textarea>
					</div>

					<div class="so-field-group">
						<label class="so-label">ที่อยู่ในการส่งสินค้า (ใช้ในการออกเอกสาร)</label>
						<textarea class="so-textarea" name="address_name" rows="3" placeholder="ระบุที่อยู่จัดส่งอย่างละเอียด..."></textarea>
					</div>

					<div class="so-field-group">
						<label class="so-label">สถานที่ติดตั้งเครื่อง</label>
						<textarea class="so-textarea" name="address_send" rows="3" placeholder="ระบุจุดติดตั้งเครื่อง/ตึก/ชั้น/ห้อง..."></textarea>
					</div>
				</div>

				<div class="so-grid-3" style="margin-bottom: 24px;">
					<div class="so-field-group">
						<label class="so-label">เลขที่เอกสาร / Serial Number เครื่อง</label>
						<textarea name="product_sn" class="so-textarea" id="product_sn" rows="3" placeholder="ระบุ Serial Number หรือเลขที่เอกสารอ้างอิง..."></textarea>
					</div>

					<div class="so-field-group">
						<label class="so-label">สินค้า / เอกสารที่จัดส่ง</label>
						<textarea name="product" class="so-textarea" id="product" rows="3" placeholder="ระบุรายละเอียดสินค้าหรือเอกสาร..."></textarea>
					</div>

					<div class="so-field-group">
						<label class="so-label">รายละเอียดเพิ่มเติม / หมายเหตุ</label>
						<textarea name="description" class="so-textarea" id="description" rows="3" placeholder="รายละเอียดหรือหมายเหตุเพิ่มเติม..."></textarea>
					</div>
				</div>

				<!-- Detailed Assessment -->
				<fieldset class="so-fieldset" style="border: 1px solid #EFEBEF; margin-top: 16px;">
					<legend class="so-legend">
						<label class="so-checkbox-label" style="font-weight: 600;">
							<input type="checkbox" name="more" id="more" value="1">
							แบบประเมินรายละเอียดเส้นทางและการเข้าติดตั้งสินค้า
						</label>
					</legend>

					<div id="more-2" style="display:none; margin-top: 20px;">
						<div class="so-grid-3">

							<!-- Column 1 -->
							<div class="so-field-group" style="gap: 12px;">
								<span style="font-size: 13px; font-weight: 600; color: #612989; border-bottom: 1px solid #EFEBEF; padding-bottom: 6px;">สภาพทางเข้าและลักษณะภายนอก</span>

								<label class="so-checkbox-label"><input type="checkbox" name="runway" id="runway" value="1"> ติดถนนรันเวย์</label>
								<label class="so-checkbox-label"><input type="checkbox" name="road" id="road" value="1"> ติดถนนวิ่งสวนกัน</label>
								<label class="so-checkbox-label"><input type="checkbox" name="soy" id="soy" value="1"> เข้าซอย</label>

								<div class="so-field-group">
									<label class="so-label">ทางเข้ากว้าง (เมตร)</label>
									<input name="soy_big" class="so-input" type='text' id="soy_big" placeholder="ตัวเลขความกว้าง (เมตร)" />
								</div>

								<label class="so-checkbox-label"><input type="checkbox" name="height_ltd" id="height_ltd" value="1"> มีตัวจำกัดความสูง</label>
								<label class="so-checkbox-label"><input type="checkbox" name="car_load" id="car_load" value="1"> รถยนต์สามารถเข้าได้</label>
								<label class="so-checkbox-label"><input type="checkbox" name="no_car_road" id="no_car_road" value="1"> รถยนต์เข้าไม่ได้ สามารถจอดได้ที่</label>
								<input name="car_park" class="so-input" type='text' id="car_park" placeholder="ระบุสถานที่จอดรถ..." />

								<span style="font-size: 13px; font-weight: 600; color: #612989; border-top: 1px solid #EFEBEF; padding-top: 12px; margin-top: 6px; padding-bottom: 6px;">การจอดรถและพื้นที่หน้าบ้าน</span>
								<label class="so-checkbox-label"><input type="checkbox" name="car_road" id="car_road" value="1"> จอดรถข้างถนน</label>
								<label class="so-checkbox-label"><input type="checkbox" name="car_home" id="car_home" value="1"> จอดรถหน้าบ้านได้</label>

								<div class="so-field-group">
									<label class="so-label">ประตูหน้าบ้านสูง (เมตร)</label>
									<input name="door_long" class="so-input" type='text' id="door_long" placeholder="ความสูงประตูหน้าบ้าน (เมตร)" />
								</div>

								<label class="so-checkbox-label"><input type="checkbox" name="slope" id="slope" value="1"> มีทางราบก่อนประตูบ้าน</label>
								<label class="so-checkbox-label"><input type="checkbox" name="bundai" id="bundai" value="1"> มีบันไดก่อนประตูบ้าน</label>

								<div class="so-field-group">
									<label class="so-label">จำนวนบันไดก่อนเข้าบ้าน (ขั้น)</label>
									<input name="unit_bundai" class="so-input" type='text' id="unit_bundai" placeholder="ระบุจำนวนขั้น..." />
								</div>

								<div class="so-field-group">
									<label class="so-label">ประตูบ้านกว้าง (เมตร)</label>
									<input name="door_bigger" class="so-input" type='text' id="door_bigger" placeholder="ความกว้างประตูบ้าน (เมตร)" />
								</div>

								<div class="so-field-group">
									<label class="so-label">ประตูบ้านสูง (เมตร)</label>
									<input name="door_longer" class="so-input" type='text' id="door_longer" placeholder="ความสูงประตูบ้าน (เมตร)" />
								</div>

								<div class="so-field-group">
									<label class="so-label">ประตูห้องกว้าง (เมตร)</label>
									<input name="room_bigger" class="so-input" type='text' id="room_bigger" placeholder="ความกว้างประตูห้อง (เมตร)" />
								</div>

								<div class="so-field-group">
									<label class="so-label">ประตูห้องสูง (เมตร)</label>
									<input name="room_longer" class="so-input" type='text' id="room_longer" placeholder="ความสูงประตูห้อง (เมตร)" />
								</div>
							</div>

							<!-- Column 2 -->
							<div class="so-field-group" style="gap: 12px;">
								<span style="font-size: 13px; font-weight: 600; color: #612989; border-bottom: 1px solid #EFEBEF; padding-bottom: 6px;">ลักษณะโครงสร้างและจุดติดตั้ง</span>

								<div class="so-field-group">
									<label class="so-label">ลักษณะประเภทของประตูบ้าน</label>
									<input name="type_door" class="so-input" type='text' id="type_door" placeholder="เช่น ประตูบานเลื่อน, ประตูไม้บานคู่..." />
								</div>

								<div class="so-field-group">
									<label class="so-label">ชนิดของพื้นบ้าน (ชั้นที่จะติดตั้ง)</label>
									<input name="home_type" class="so-input" type='text' id="home_type" placeholder="เช่น พื้นไม้, กระเบื้อง, ปูน..." />
								</div>

								<div class="so-field-group">
									<label class="so-label">ติดตั้งสินค้าที่ชั้นที่เท่าไหร่</label>
									<input name="install" class="so-input" type='text' id="install" placeholder="ระบุชั้น เช่น ชั้น 1, ชั้น 2..." />
								</div>

								<label class="so-checkbox-label"><input type="checkbox" name="bundai_install" id="bundai_install" value="1"> บันไดกว้าง (สำหรับการขนส่ง)</label>

								<div class="so-field-group">
									<label class="so-label">ความกว้างของบันได (เมตร)</label>
									<input name="bundai_big" class="so-input" type='text' id="bundai_big" placeholder="ระบุความกว้างบันได (เมตร)..." />
								</div>

								<div class="so-field-group">
									<label class="so-label">ช่วงหักมุมบันไดกว้าง</label>
									<input name="bundai_hug" class="so-input" type='text' id="bundai_hug" placeholder="ช่วงหักมุมกว้าง..." />
								</div>

								<div class="so-field-group">
									<label class="so-label">ชนิดของบันได</label>
									<input name="type_bundai" class="so-input" type='text' id="type_bundai" placeholder="เช่น บันไดวน, บันไดตรงแบบชัน..." />
								</div>

								<label class="so-checkbox-label"><input type="checkbox" name="lip" id="lip" value="1"> ลิฟท์ขนสินค้า / ลิฟท์กว้าง</label>

								<div class="so-field-group">
									<label class="so-label">ความกว้างลิฟท์ (เมตร)</label>
									<input name="lip_big" class="so-input" type='text' id="lip_big" placeholder="ความกว้างประตูลิฟท์..." />
								</div>

								<div class="so-field-group">
									<label class="so-label">ความสูงลิฟท์ (เมตร)</label>
									<input name="lip_long" class="so-input" type='text' id="lip_long" placeholder="ความสูงลิฟท์..." />
								</div>

								<div class="so-field-group">
									<label class="so-label">ลิฟท์รับน้ำหนักสูงสุดได้ (กิโลกรัม)</label>
									<input name="lip_weight" class="so-input" type='text' id="lip_weight" placeholder="ระบุการรับน้ำหนักสูงสุด..." />
								</div>
							</div>

							<!-- Column 3 -->
							<div class="so-field-group" style="gap: 12px;">
								<span style="font-size: 13px; font-weight: 600; color: #612989; border-bottom: 1px solid #EFEBEF; padding-bottom: 6px;">อุปสรรคและรายละเอียดเครื่องมือเพิ่มเติม</span>

								<label class="so-checkbox-label"><input type="checkbox" name="up" id="up" value="1"> ขึ้นลิฟท์ได้</label>
								<label class="so-checkbox-label"><input type="checkbox" name="no_up" id="no_up" value="1"> ขึ้นลิฟท์ไม่ได้</label>
								<label class="so-checkbox-label"><input type="checkbox" name="head_bad" id="head_bad" value="1"> ต้องถอดหัวเตียง-ท้ายเตียง</label>
								<label class="so-checkbox-label"><input type="checkbox" name="want_employee" id="want_employee" value="1"> ต้องการเจ้าหน้าที่ช่วยย้ายเฟอร์นิเจอร์</label>

								<div class="so-field-group">
									<label class="so-label">จำนวนคนที่ต้องการใช้เพิ่ม (คน)</label>
									<input name="employee_unit" class="so-input" type='text' id="employee_unit" placeholder="ระบุจำนวนคน..." />
								</div>

								<div class="so-field-group">
									<label class="so-label">เฟอร์นิเจอร์ที่ต้องการย้าย</label>
									<input name="ferniger_name" class="so-input" type='text' id="ferniger_name" placeholder="ระบุชื่อเฟอร์นิเจอร์..." />
								</div>

								<div class="so-field-group">
									<label class="so-label">สถานที่ย้ายเฟอร์นิเจอร์ไปไว้</label>
									<input name="ferniger_address" class="so-input" type='text' id="ferniger_address" placeholder="จุดที่จะให้ย้ายเฟอร์นิเจอร์ไปไว้..." />
								</div>

								<label class="so-checkbox-label"><input type="checkbox" name="want_ex" id="want_ex" value="1"> ต้องเตรียมอุปกรณ์ไปถอดประกอบเครื่อง</label>
								<label class="so-checkbox-label"><input type="checkbox" name="want_credit" id="want_credit" value="1"> ต้องเตรียมเครื่องรูดบัตร (เครดิตหน้างาน)</label>

								<div class="so-field-group">
									<label class="so-label">เครื่องรูดของธนาคาร</label>
									<input name="bank" class="so-input" type='text' id="bank" placeholder="ระบุธนาคาร..." />
								</div>

								<label class="so-checkbox-label"><input type="checkbox" name="want_prem" id="want_prem" value="1"> ต้องการฟิล์มยืดสำหรับห่อเตียง/ที่นอนเก่า</label>

								<div class="so-field-group">
									<label class="so-label">รายละเอียดประเมินเพิ่มเติม</label>
									<textarea name="description_ja" class="so-textarea" id="description_ja" rows="2" placeholder="ระบุรายละเอียดทางเดินหรืออุปสรรคติดตั้งอื่นๆ..."></textarea>
								</div>
							</div>

						</div>
					</div>
				</fieldset>

			</div><!-- cs -->

			<div id="cs1" class="w3-container city1" style="display:none">
				<fieldset class="so-fieldset" style="margin-top: 16px;">
					<legend class="so-legend">รายละเอียดการจัดส่ง (เพิ่มเติม1)</legend>
					<div class="so-grid-3" style="margin-bottom: 0;">
						<div class="so-field-group">
							<label class="so-label">ชื่อผู้ติดต่อ 1</label>
							<input name="customer_name1" class="so-input" type="text" id="customer_name1" placeholder="ชื่อผู้ติดต่อ...">
						</div>
						<div class="so-field-group">
							<label class="so-label">เบอร์โทรลูกค้า 1</label>
							<input name="customer_tel1" class="so-input" type="text" id="customer_tel1" placeholder="เบอร์โทรศัพท์...">
						</div>
						<div class="so-field-group">
							<label class="so-label">ที่อยู่ในการส่งสินค้า 1</label>
							<input type="text" class="so-input" name="address_name1" id="address_name1" placeholder="ที่อยู่จัดส่งเพิ่มเติม...">
						</div>
					</div>
				</fieldset>
				<fieldset class="so-fieldset" style="margin-top: 16px;">
					<legend class="so-legend">รายละเอียดการจัดส่ง (เพิ่มเติม2)</legend>
					<div class="so-grid-3" style="margin-bottom: 0;">
						<div class="so-field-group">
							<label class="so-label">ชื่อผู้ติดต่อ 2</label>
							<input name="customer_name2" class="so-input" type="text" id="customer_name2" placeholder="ชื่อผู้ติดต่อ...">
						</div>
						<div class="so-field-group">
							<label class="so-label">เบอร์โทรลูกค้า 2</label>
							<input name="customer_tel2" class="so-input" type="text" id="customer_tel2" placeholder="เบอร์โทรศัพท์...">
						</div>
						<div class="so-field-group">
							<label class="so-label">ที่อยู่ในการส่งสินค้า 2</label>
							<input type="text" class="so-input" name="address_name2" id="address_name2" placeholder="ที่อยู่จัดส่งเพิ่มเติม...">
						</div>
					</div>
				</fieldset>
				<fieldset class="so-fieldset" style="margin-top: 16px;">
					<legend class="so-legend">รายละเอียดการจัดส่ง (เพิ่มเติม3)</legend>
					<div class="so-grid-3" style="margin-bottom: 0;">
						<div class="so-field-group">
							<label class="so-label">ชื่อผู้ติดต่อ 3</label>
							<input name="customer_name3" class="so-input" type="text" id="customer_name3" placeholder="ชื่อผู้ติดต่อ...">
						</div>
						<div class="so-field-group">
							<label class="so-label">เบอร์โทรลูกค้า 3</label>
							<input name="customer_tel3" class="so-input" type="text" id="customer_tel3" placeholder="เบอร์โทรศัพท์...">
						</div>
						<div class="so-field-group">
							<label class="so-label">ที่อยู่ในการส่งสินค้า 3</label>
							<input type="text" class="so-input" name="address_name3" id="address_name3" placeholder="ที่อยู่จัดส่งเพิ่มเติม...">
						</div>
					</div>
				</fieldset>
				<fieldset class="so-fieldset" style="margin-top: 16px;">
					<legend class="so-legend">รายละเอียดการจัดส่ง (เพิ่มเติม4)</legend>
					<div class="so-grid-3" style="margin-bottom: 0;">
						<div class="so-field-group">
							<label class="so-label">ชื่อผู้ติดต่อ 4</label>
							<input name="customer_name4" class="so-input" type="text" id="customer_name4" placeholder="ชื่อผู้ติดต่อ...">
						</div>
						<div class="so-field-group">
							<label class="so-label">เบอร์โทรลูกค้า 4</label>
							<input name="customer_tel4" class="so-input" type="text" id="customer_tel4" placeholder="เบอร์โทรศัพท์...">
						</div>
						<div class="so-field-group">
							<label class="so-label">ที่อยู่ในการส่งสินค้า 4</label>
							<input type="text" class="so-input" name="address_name4" id="address_name4" placeholder="ที่อยู่จัดส่งเพิ่มเติม...">
						</div>
					</div>
				</fieldset>
				<fieldset class="so-fieldset" style="margin-top: 16px;">
					<legend class="so-legend">รายละเอียดการจัดส่ง (เพิ่มเติม5)</legend>
					<div class="so-grid-3" style="margin-bottom: 0;">
						<div class="so-field-group">
							<label class="so-label">ชื่อผู้ติดต่อ 5</label>
							<input name="customer_name5" class="so-input" type="text" id="customer_name5" placeholder="ชื่อผู้ติดต่อ...">
						</div>
						<div class="so-field-group">
							<label class="so-label">เบอร์โทรลูกค้า 5</label>
							<input name="customer_tel5" class="so-input" type="text" id="customer_tel5" placeholder="เบอร์โทรศัพท์...">
						</div>
						<div class="so-field-group">
							<label class="so-label">ที่อยู่ในการส่งสินค้า 5</label>
							<input type="text" class="so-input" name="address_name5" id="address_name5" placeholder="ที่อยู่จัดส่งเพิ่มเติม...">
						</div>
					</div>
				</fieldset>
				<fieldset class="so-fieldset" style="margin-top: 16px;">
					<legend class="so-legend">รายละเอียดการจัดส่ง (เพิ่มเติม6)</legend>
					<div class="so-grid-3" style="margin-bottom: 0;">
						<div class="so-field-group">
							<label class="so-label">ชื่อผู้ติดต่อ 6</label>
							<input name="customer_name6" class="so-input" type="text" id="customer_name6" placeholder="ชื่อผู้ติดต่อ...">
						</div>
						<div class="so-field-group">
							<label class="so-label">เบอร์โทรลูกค้า 6</label>
							<input name="customer_tel6" class="so-input" type="text" id="customer_tel6" placeholder="เบอร์โทรศัพท์...">
						</div>
						<div class="so-field-group">
							<label class="so-label">ที่อยู่ในการส่งสินค้า 6</label>
							<input type="text" class="so-input" name="address_name6" id="address_name6" placeholder="ที่อยู่จัดส่งเพิ่มเติม...">
						</div>
					</div>
				</fieldset>
				<fieldset class="so-fieldset" style="margin-top: 16px;">
					<legend class="so-legend">รายละเอียดการจัดส่ง (เพิ่มเติม7)</legend>
					<div class="so-grid-3" style="margin-bottom: 0;">
						<div class="so-field-group">
							<label class="so-label">ชื่อผู้ติดต่อ 7</label>
							<input name="customer_name7" class="so-input" type="text" id="customer_name7" placeholder="ชื่อผู้ติดต่อ...">
						</div>
						<div class="so-field-group">
							<label class="so-label">เบอร์โทรลูกค้า 7</label>
							<input name="customer_tel7" class="so-input" type="text" id="customer_tel7" placeholder="เบอร์โทรศัพท์...">
						</div>
						<div class="so-field-group">
							<label class="so-label">ที่อยู่ในการส่งสินค้า 7</label>
							<input type="text" class="so-input" name="address_name7" id="address_name7" placeholder="ที่อยู่จัดส่งเพิ่มเติม...">
						</div>
					</div>
				</fieldset>
				<fieldset class="so-fieldset" style="margin-top: 16px;">
					<legend class="so-legend">รายละเอียดการจัดส่ง (เพิ่มเติม8)</legend>
					<div class="so-grid-3" style="margin-bottom: 0;">
						<div class="so-field-group">
							<label class="so-label">ชื่อผู้ติดต่อ 8</label>
							<input name="customer_name8" class="so-input" type="text" id="customer_name8" placeholder="ชื่อผู้ติดต่อ...">
						</div>
						<div class="so-field-group">
							<label class="so-label">เบอร์โทรลูกค้า 8</label>
							<input name="customer_tel8" class="so-input" type="text" id="customer_tel8" placeholder="เบอร์โทรศัพท์...">
						</div>
						<div class="so-field-group">
							<label class="so-label">ที่อยู่ในการส่งสินค้า 8</label>
							<input type="text" class="so-input" name="address_name8" id="address_name8" placeholder="ที่อยู่จัดส่งเพิ่มเติม...">
						</div>
					</div>
				</fieldset>
				<fieldset class="so-fieldset" style="margin-top: 16px;">
					<legend class="so-legend">รายละเอียดการจัดส่ง (เพิ่มเติม9)</legend>
					<div class="so-grid-3" style="margin-bottom: 0;">
						<div class="so-field-group">
							<label class="so-label">ชื่อผู้ติดต่อ 9</label>
							<input name="customer_name9" class="so-input" type="text" id="customer_name9" placeholder="ชื่อผู้ติดต่อ...">
						</div>
						<div class="so-field-group">
							<label class="so-label">เบอร์โทรลูกค้า 9</label>
							<input name="customer_tel9" class="so-input" type="text" id="customer_tel9" placeholder="เบอร์โทรศัพท์...">
						</div>
						<div class="so-field-group">
							<label class="so-label">ที่อยู่ในการส่งสินค้า 9</label>
							<input type="text" class="so-input" name="address_name9" id="address_name9" placeholder="ที่อยู่จัดส่งเพิ่มเติม...">
						</div>
					</div>
				</fieldset>
			</div>






		</div>
		</div>
		<div style="width: 100%; background-color: white; padding: 16px 24px; display: flex; gap: 16px; justify-content: flex-end; box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.05); align-items: center; border-top: 1px solid #EBEBEB; margin-top: 24px; box-sizing: border-box;">
			<div style="max-width: 1200px; width: 100%; display: flex; gap: 16px; justify-content: flex-end; margin: 0 auto; padding-right: 24px;">
				<button type="submit" name="submit" class="btn-submit-so" style="background-color: #612989; color: white; border: none; border-radius: 24px; padding: 12px 32px; font-family: 'Prompt', sans-serif; font-size: 16px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(97, 41, 137, 0.2);">
					<i class="far fa-paper-plane"></i> Submit
				</button>
				<button type="button" name="save_draft" style="background-color: white; color: #612989; border: 1px solid #EBEBEB; border-radius: 24px; padding: 12px 32px; font-family: 'Prompt', sans-serif; font-size: 16px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
					<i class="far fa-save"></i> Save Draft
				</button>
			</div>
		</div>
	</form>

	<script>
		// Switch top tabs
		function switchSoTab(evt, tabId) {
			evt.preventDefault();
			var i, tabcontent, tablinks;

			tabcontent = document.getElementsByClassName("so-tab-content");
			for (i = 0; i < tabcontent.length; i++) {
				tabcontent[i].classList.remove("active");
			}

			tablinks = document.getElementsByClassName("so-tab-btn");
			for (i = 0; i < tablinks.length; i++) {
				tablinks[i].classList.remove("active");
			}

			document.getElementById(tabId).classList.add("active");
			evt.currentTarget.classList.add("active");
		}

		// Toggle loan/reserve collapsible section
		function toggleClearSection() {
			// var sect = document.getElementById('clear_loan_reserve_section');
			// if (sect.classList.contains('show')) {
			// 	sect.classList.remove('show');
			// } else {
			// 	sect.classList.add('show');
			// }
		}

		// Handle custom pill style toggle switches
		function updateToggleStyle(checkbox) {
			var label = document.getElementById('lbl-' + checkbox.id);
			if (label) {
				if (checkbox.checked) {
					label.classList.add('active');
				} else {
					label.classList.remove('active');
				}
			}
		}

		// Attach event listeners to toggle switches on load
		document.addEventListener('DOMContentLoaded', function() {
			['que_ckk', 'have_order', 'plan_ckk', 'repeat_cus', 'full_bill'].forEach(function(id) {
				var cb = document.getElementById(id);
				if (cb) {
					updateToggleStyle(cb);
					cb.addEventListener('change', function() {
						updateToggleStyle(this);
					});
				}
			});
		});

		// Override openCity1 to add modern active tab highlighting
		var originalOpenCity1 = openCity1;
		openCity1 = function(cityName, elem) {
			if (typeof originalOpenCity1 === 'function') {
				var i;
				var x = document.getElementsByClassName("city1");
				for (i = 0; i < x.length; i++) {
					x[i].style.display = "none";
				}
				var target = document.getElementById(cityName);
				if (target) target.style.display = "block";
			}

			var buttons = document.querySelectorAll('.bottom-tab-btn');
			buttons.forEach(function(btn) {
				btn.classList.remove('active');
			});
			if (elem) {
				elem.classList.add('active');
			} else {
				var targetBtn = document.querySelector('.bottom-tab-btn[onclick*="' + cityName + '"]');
				if (targetBtn) targetBtn.classList.add('active');
			}
		};
	</script>


	<div id="cr_bar"> <?php include "foot.php"; ?></div>
</body>



<script type="text/javascript">
	function make_autocom(autoObj, showObj) {
		var mkAutoObj = autoObj;
		var mkSerValObj = showObj;
		new Autocomplete(mkAutoObj, function() {
			this.setValue = function(id) {
				document.getElementById(mkSerValObj).value = id;
			}
			if (this.isModified)
				this.setValue("");
			if (this.value.length < 1 && this.isNotClick)
				return;
			return "data_bill_name.php?bill_search=" + encodeURIComponent(this.value);
		});
	}

	// การใช้งาน
	// make_autocom(" id ของ input ตัวที่ต้องการกำหนด "," id ของ input ตัวที่ต้องการรับค่า");
	make_autocom("bill_id", "h_bill_id");
</script>


<script type="text/javascript">
	function make_autocom(autoObj, showObj) {
		var mkAutoObj = autoObj;
		var mkSerValObj = showObj;
		new Autocomplete(mkAutoObj, function() {
			this.setValue = function(id) {
				document.getElementById(mkSerValObj).value = id;
			}
			if (this.isModified)
				this.setValue("");
			if (this.value.length < 1 && this.isNotClick)
				return;
			return "data_sale1.php?employee_name_search=" + encodeURIComponent(this.value);
		});
	}

	// การใช้งาน
	// make_autocom(" id ของ input ตัวที่ต้องการกำหนด "," id ของ input ตัวที่ต้องการรับค่า");
	make_autocom("employee_name", "h_employee_name");
</script>


<script>
	$('#more').click(function() {
		if ($(this).is(":checked")) {
			$("#more-2").show();
		} else {
			$("#more-2").hide();
		}
	});
</script>


<script>
	document.getElementById('have_order').addEventListener('change', function() {
		const deliveryInput = document.getElementById('delivery_contract');

		if (this.checked && !deliveryInput.value) {
			alert("กรุณาระบุวันที่ 'กำหนดส่งตามสัญญา'");
			deliveryInput.focus();
		}
	});

	// ป้องกันการ submit form ถ้ามีการติ๊กแต่ไม่กรอกวันที่
	document.querySelector('form')?.addEventListener('submit', function(e) {
		const checkbox = document.getElementById('have_order');
		const deliveryInput = document.getElementById('delivery_contract');

		if (checkbox.checked && !deliveryInput.value) {
			e.preventDefault();
			alert("คุณได้เลือก 'มีออร์เดอร์ฝาก' กรุณาระบุวัน 'กำหนดส่งตามสัญญา'");
			deliveryInput.focus();
		}
	});
</script>


<script>
	let lastOkValues = {}; // เก็บค่าก่อนหน้า เพื่อย้อนกลับได้

	function num(v) {
		return parseFloat(String(v || "").replace(/,/g, '')) || 0;
	}

	function calcTotal() {
		let total = 0;
		for (let i = 1; i <= 20; i++) {
			const el = document.getElementById('sum_amount' + i);
			if (el) total += num(el.value);
		}
		document.getElementById('sum_amount_total').value = total.toFixed(2);
		return total;
	}

	function checkOverLimit(changedId) {
		setTimeout(() => {
			const total = calcTotal();
			const limit = num(document.getElementById('sum_ca')?.value);

			if (limit > 0 && total > limit) {

				// โชว์ Popup แทน alert
				showOverLimitPopup(total, limit);

				// (เลือกได้) จะย้อนค่าเดิมหรือไม่ก็ได้
				const el = document.getElementById(changedId);
				if (el && lastOkValues[changedId] !== undefined) {
					el.value = lastOkValues[changedId];
				}
				calcTotal();
				if (el) {
					el.focus();
					el.select?.();
				}

			} else {
				const el = document.getElementById(changedId);
				if (el) lastOkValues[changedId] = el.value;
			}
		}, 30);
	}


	function bindRow(i) {
		const ids = ['sale_count', 'product_price', 'discount_unit'].map(x => x + i);
		ids.forEach(id => {
			const el = document.getElementById(id);
			if (!el) return;

			// เก็บค่าเริ่มต้นไว้ก่อน
			lastOkValues[id] = el.value;

			el.addEventListener('focus', () => lastOkValues[id] = el.value);
			el.addEventListener('input', () => checkOverLimit(id));
			el.addEventListener('change', () => checkOverLimit(id));
		});
	}

	document.addEventListener('DOMContentLoaded', () => {
		for (let i = 1; i <= 20; i++) bindRow(i);
		calcTotal();
	});
</script>

<script>
	let modalLock = false;

	function fmt2(n) {
		return (Number(n) || 0).toLocaleString('th-TH', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
	}


	function showOverLimitPopup(total, limit) {
		if (modalLock) return;
		modalLock = true;

		document.getElementById('ol_total').textContent = fmt2(total);
		document.getElementById('ol_limit').textContent = fmt2(limit);

		document.getElementById('overLimitModal').style.display = 'block';
	}

	function goMainSuphos() {
		window.location.href = 'main_suphos_so.php';
	}
</script>

<style>
	/* Overlay */
	#overLimitModal {
		display: none;
		position: fixed;
		z-index: 99999;
		left: 0;
		top: 0;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, .45);
	}

	/* Modal box */
	#overLimitModal .box {
		width: min(520px, 92vw);
		background: #fff;
		border-radius: 14px;
		margin: 7vh auto;
		padding: 26px 22px 22px;
		position: relative;
		text-align: center;
		box-shadow: 0 8px 30px rgba(0, 0, 0, .25);
		font-family: Arial, sans-serif;
	}

	#overLimitModal .closeX {
		position: absolute;
		right: 14px;
		top: 10px;
		font-size: 28px;
		cursor: pointer;
		color: #333;
		line-height: 1;
	}

	#overLimitModal .icon {
		width: 86px;
		height: 86px;
		margin: 6px auto 10px;
	}

	#overLimitModal .title {
		font-size: 28px;
		margin: 10px 0 18px;
	}

	#overLimitModal .row {
		font-size: 18px;
		margin: 10px 0;
	}

	#overLimitModal .btnBack {
		margin-top: 18px;
		padding: 12px 26px;
		border: none;
		border-radius: 22px;
		background: #612989;
		color: #fff;
		font-size: 16px;
		cursor: pointer;
	}

	#overLimitModal .btnBack:hover {
		opacity: .92;
	}

	/* ถ้าอยากให้ทั้งหน้าเป็น Prompt */
	body {
		font-family: 'Prompt', sans-serif;
	}

	/* หรือกำหนดเฉพาะ popup */
	.overlimitModal,
	.overlimitModal * {
		font-family: 'Prompt', sans-serif !important;
	}

	/* ปุ่มกลับ */
	.btnBack {
		font-family: 'Prompt', sans-serif !important;
		font-weight: 500;
	}
</style>

<div id="overLimitModal">
	<div class="box">
		<div class="closeX" onclick="goMainSuphos()">×</div>

		<!-- icon (SVG) -->
		<svg class="icon" viewBox="0 0 64 64" aria-hidden="true">
			<path d="M32 6 L60 58 H4 Z" fill="#ff3b30" />
			<rect x="29" y="22" width="6" height="18" rx="3" fill="#fff" />
			<circle cx="32" cy="46" r="3.2" fill="#fff" />
		</svg>

		<div class="title">กรุณาติดต่อบัญชี</div>
		<div class="row">เพื่อขอเพิ่มวงเงิน เนื่องจากยอดรวมสินค้าเกินวงเงินคงเหลือ</div>


		<div class="row">ยอดรวมสินค้า : <b id="ol_total">0</b></div>
		<div class="row">วงเงินคงเหลือ : <b id="ol_limit">0</b></div>

		<button type="button" class="btnBack" onclick="goMainSuphos()">กลับสู่หน้าหลัก</button>
	</div>
</div>