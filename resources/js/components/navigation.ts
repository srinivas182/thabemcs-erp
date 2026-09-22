/**
 * Sidebar groups follow the development cycle, so people find modules
 * in the order the business uses them. Modules the company has not
 * enabled are hidden.
 */
export const NAV_GROUPS: { title: string; modules: string[] }[] = [
    { title: 'Develop', modules: ['projects', 'feasibility', 'funding', 'land', 'approvals'] },
    { title: 'Build', modules: ['suppliers', 'procurement', 'site', 'safety', 'workforce', 'plant'] },
    { title: 'Money', modules: ['finance'] },
    { title: 'Sell & rent', modules: ['sales', 'rentals'] },
    { title: 'Records', modules: ['documents', 'reporting'] },
];
