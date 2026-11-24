/**
 * Browser end-to-end checks (Playwright). Usage:
 *   BASE=http://127.0.0.1:8080 OUT=/tmp/shots node tests/e2e/portal.e2e.js
 * Rate limits apply (5 registrations/hour/IP): clear `_transient_rsp_rl_*` options first on a test site.
 * Requires: portal pages created (`wp rsp setup-pages --all`), demo plans, the offline
 * "cheque" gateway enabled. Prints PASS/FAIL lines and writes screenshots to OUT.
 */
const { chromium, devices } = require( 'playwright' );
const fs = require( 'fs' );
const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const OUT = process.env.OUT || '/tmp/rsp-shots';
fs.mkdirSync( OUT, { recursive: true } );
const results = [];
const t = ( name, ok, detail ) => { results.push( { name, ok: !! ok, detail: detail || '' } ); console.log( ( ok ? 'PASS ' : 'FAIL ' ) + name + ( ok || ! detail ? '' : ' — ' + detail ) ); };
const rand = Math.random().toString( 36 ).slice( 2, 8 );

async function noOverflow( page ) {
	return page.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth + 1 );
}

( async () => {
	const browser = await chromium.launch();
	// ---------- Desktop customer journey ----------
	const ctx = await browser.newContext( { viewport: { width: 1366, height: 900 } } );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );

	await page.goto( BASE + '/portal/' );
	t( 'guest sees sign-in form on portal', await page.locator( 'input[name="pwd"]' ).count() === 1 );

	await page.goto( BASE + '/portal-register/' );
	const email = `e2e-${ rand }@example.test`;
	await page.fill( 'input[name="first_name"]', 'Dana' );
	await page.fill( 'input[name="email"]', email );
	await page.fill( 'input[name="password"]', 'Secret-pass-123' );
	await Promise.all( [ page.waitForNavigation(), page.click( 'button[type="submit"]' ) ] );
	t( 'registration signs in and opens the portal', ( await page.textContent( '.rsp-main h1' ) || '' ).includes( 'Dana' ), page.url() );
	await page.screenshot( { path: OUT + '/desktop-dashboard-dark.png', fullPage: true } );

	await page.goto( BASE + '/portal-plans/' );
	t( 'plans page lists 4 plans', await page.locator( '.rsp-plan' ).count() === 4 );
	await page.screenshot( { path: OUT + '/desktop-plans.png', fullPage: true } );
	await page.fill( 'input[name="nickname"]', 'E2E Lounge' );
	await Promise.all( [ page.waitForNavigation(), page.locator( '.rsp-plan button[name="plan"]' ).nth( 1 ).click() ] );
	t( 'Select plan hands off to WooCommerce checkout', /checkout/.test( page.url() ), page.url() );
	await page.waitForTimeout( 2500 );
	await page.screenshot( { path: OUT + '/checkout.png', fullPage: true } );
	// Block checkout (WooCommerce default) — fill minimal billing fields when present.
	const fill = async ( sel, v ) => { const l = page.locator( sel ); if ( await l.count() ) { await l.first().fill( v ); } };
	await fill( '#billing-first_name, #billing_first_name', 'Dana' );
	await fill( '#billing-last_name, #billing_last_name', 'Tester' );
	await fill( '#billing-address_1, #billing_address_1', '1 Test Street' );
	await fill( '#billing-city, #billing_city', 'Testville' );
	await fill( '#billing-postcode, #billing_postcode', '10001' );
	const st = page.locator( '#billing-state input, #billing-state select, #billing_state' );
	if ( await st.count() ) { try { await st.first().selectOption( 'NY' ); } catch ( e ) { try { await st.first().fill( 'New York' ); await page.keyboard.press( 'Enter' ); } catch ( e2 ) {} } }
	await fill( '#email, #billing_email', email );
	const placeBtn = page.locator( '.wc-block-components-checkout-place-order-button, #place_order' );
	await placeBtn.first().click();
	await page.waitForURL( /order-received/, { timeout: 30000 } ).catch( () => {} );
	t( 'order placed with test gateway (thank-you page)', /order-received/.test( page.url() ), page.url() );
	const orderId = ( page.url().match( /order-received\/(\d+)/ ) || [] )[ 1 ];
	fs.writeFileSync( OUT + '/order-id.txt', orderId || '' );

	await page.goto( BASE + '/portal-accounts/' );
	const accText = await page.textContent( '.rsp-main' );
	t( 'thank-you page did not activate: account shows awaiting payment', /Awaiting payment/.test( accText ) && ! /E2E Lounge[\s\S]*Active/.test( accText ) );
	await page.goto( BASE + '/portal-transactions/' );
	t( 'transactions show payment and activation status', await page.locator( '.rsp-table tbody tr' ).count() >= 1 );
	await page.screenshot( { path: OUT + '/desktop-transactions.png', fullPage: true } );

	await page.goto( BASE + '/portal-apps/' );
	await page.locator( '.rsp-device-tab' ).nth( 1 ).click();
	t( 'device tabs switch panels', await page.locator( '.rsp-device-panel:not([hidden])' ).count() === 1 );
	await page.screenshot( { path: OUT + '/desktop-apps.png', fullPage: true } );

	await page.goto( BASE + '/portal-referrals/' );
	const code = await page.textContent( '.rsp-code-big' );
	t( 'referral code shown', /^R[A-Z0-9]{9}$/.test( ( code || '' ).trim() ) );
	await page.screenshot( { path: OUT + '/desktop-referrals.png', fullPage: true } );

	await page.goto( BASE + '/portal-support/?new=1' );
	await page.fill( 'input[name="subject"]', 'E2E ticket' );
	await page.fill( 'textarea[name="body"]', 'Testing the support flow end to end.' );
	await page.setInputFiles( 'input[type="file"]', { name: 'note.txt', mimeType: 'text/plain', buffer: Buffer.from( 'hello attachment' ) } );
	await Promise.all( [ page.waitForNavigation(), page.click( 'form.rsp-form button[type="submit"]' ) ] );
	t( 'ticket created with attachment', /ticket=\d+/.test( page.url() ) && await page.locator( '.rsp-attachment' ).count() === 1 );
	const ticketId = ( page.url().match( /ticket=(\d+)/ ) || [] )[ 1 ];
	const attHref = await page.locator( '.rsp-attachment' ).getAttribute( 'href' );
	const own = await page.request.get( attHref );
	t( 'owner can download own attachment', own.status() === 200 && ( await own.text() ) === 'hello attachment' );
	fs.writeFileSync( OUT + '/att-url.txt', attHref );

	// Light theme toggle persists.
	await page.goto( BASE + '/portal/' );
	await page.click( '[data-rsp-theme-toggle]' );
	const theme = await page.getAttribute( '.rsp-shell', 'data-rsp-theme' );
	await page.reload();
	t( 'theme toggle persists across reloads', theme === await page.getAttribute( '.rsp-shell', 'data-rsp-theme' ) );
	await page.screenshot( { path: OUT + '/desktop-dashboard-toggled.png', fullPage: true } );
	t( 'no JavaScript errors on portal pages', errors.length === 0, errors.join( '; ' ) );
	await ctx.close();

	// ---------- Second customer: isolation over HTTP ----------
	const ctx2 = await browser.newContext();
	const p2 = await ctx2.newPage();
	await p2.goto( BASE + '/portal-register/' );
	await p2.fill( 'input[name="email"]', `e2e-other-${ rand }@example.test` );
	await p2.fill( 'input[name="password"]', 'Secret-pass-123' );
	await Promise.all( [ p2.waitForNavigation(), p2.click( 'button[type="submit"]' ) ] );
	const other = await p2.request.get( attHref );
	t( 'other customer cannot download the attachment (even with the URL)', other.status() >= 400 );
	await p2.goto( BASE + '/portal-support/?ticket=' + ticketId );
	t( 'other customer cannot open someone else\'s ticket thread', ! ( await p2.textContent( '.rsp-main' ) ).includes( 'E2E ticket' ) );
	const nonce = await p2.evaluate( () => window.RSP && window.RSP.nonce );
	const rest = await p2.request.get( BASE + '/wp-json/rsp/v1/accounts', { headers: { 'X-WP-Nonce': nonce } } );
	t( 'REST accounts for a new customer is empty', rest.status() === 200 && ( await rest.json() ).length === 0 );
	const anon = await ( await browser.newContext() ).request.get( BASE + '/wp-json/rsp/v1/accounts' );
	t( 'anonymous REST denied', anon.status() === 401 );
	await ctx2.close();

	// ---------- Mobile ----------
	const mctx = await browser.newContext( { ...devices[ 'iPhone 13' ] } );
	const m = await mctx.newPage();
	await m.goto( BASE + '/portal-login/' );
	await m.fill( 'input[name="log"]', email );
	await m.fill( 'input[name="pwd"]', 'Secret-pass-123' );
	await Promise.all( [ m.waitForNavigation(), m.click( 'button[type="submit"]' ) ] );
	t( 'login form works on mobile', ( await m.textContent( '.rsp-main h1' ) || '' ).includes( 'Dana' ) );
	for ( const slug of [ 'portal', 'portal-accounts', 'portal-plans', 'portal-apps', 'portal-transactions', 'portal-referrals', 'portal-support' ] ) {
		await m.goto( BASE + '/' + slug + '/' );
		t( `mobile ${ slug }: no horizontal scroll`, await noOverflow( m ) );
		await m.screenshot( { path: OUT + `/mobile-${ slug }.png`, fullPage: true } );
	}
	await mctx.close();

	// ---------- Elementor-built page ----------
	const ectx = await browser.newContext( { viewport: { width: 1280, height: 900 } } );
	const e = await ectx.newPage();
	await e.goto( BASE + '/portal-login/' );
	await e.fill( 'input[name="log"]', email );
	await e.fill( 'input[name="pwd"]', 'Secret-pass-123' );
	await Promise.all( [ e.waitForNavigation(), e.click( 'button[type="submit"]' ) ] );
	await e.goto( BASE + '/elementor-portal-test/' );
	t( 'Elementor page renders Portal Shell + Plan Cards widgets with live data', await e.locator( '.elementor-widget-rsp-portal-shell .rsp-shell' ).count() === 1 && await e.locator( '.elementor-widget-rsp-plan-cards .rsp-plan' ).count() === 4 && ( await e.textContent( '.rsp-nav' ) ).includes( 'My lines' ) );
	const accent = await e.evaluate( () => getComputedStyle( document.querySelector( '.elementor-widget-rsp-portal-shell .rsp-root' ) ).getPropertyValue( '--rsp-accent' ).trim() );
	t( 'Elementor style control overrides branding color for that widget', accent.toLowerCase() === '#e11d48', accent );
	await e.screenshot( { path: OUT + '/elementor-page.png', fullPage: true } );
	await ectx.close();

	fs.writeFileSync( OUT + '/e2e-results.json', JSON.stringify( results, null, 2 ) );
	await browser.close();
	const fail = results.filter( ( r ) => ! r.ok ).length;
	console.log( `\n${ results.length - fail } passed, ${ fail } failed` );
	process.exit( fail ? 1 : 0 );
} )();
