import { mountPackageOffers } from './package-offers';
import { mountPlatformAdmin } from './platform-admin';

const workspace = document.getElementById('offer-workspace');

if (workspace) {
    mountPackageOffers(workspace);
}

const admin = document.querySelector('[data-platform-admin]');
if (admin) {
    mountPlatformAdmin(admin);
}
