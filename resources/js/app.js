import { mountPackageOffers } from './package-offers';

const workspace = document.getElementById('offer-workspace');

if (workspace) {
    mountPackageOffers(workspace);
}
