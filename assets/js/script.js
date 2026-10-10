document.addEventListener('keydown', function (event) {
	if (event.key !== 'Escape') return;

	const aboutMenu = document.getElementById('hnAboutDropdown');
	const profileMenu = document.getElementById('hnProfileDropdown');
	const navigation = document.querySelector('.hn-nav');
	const menuButton = document.querySelector('.hn-menu-toggle');

	if (aboutMenu) aboutMenu.classList.remove('active');
	if (profileMenu) profileMenu.classList.remove('active');
	if (navigation) navigation.classList.remove('mobile-open');
	if (menuButton) menuButton.setAttribute('aria-expanded', 'false');
});
