/*
 * What a normal working day looks like: people signing in, looking at the dashboard, opening a project,
 * checking the programme and running a report.
 *
 *   k6 run -e BASE_URL=https://staging.thabekhulu.co.za -e USERS=2000 load-tests/browsing.js
 *
 * Thresholds below are the agreed targets: 95% of pages under 500 ms and fewer than 1% failures.
 */
import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const failures = new Rate('failed_requests');
const BASE = __ENV.BASE_URL || 'http://localhost';
const USERS = Number(__ENV.USERS || 500);

export const options = {
    stages: [
        { duration: '3m', target: Math.round(USERS * 0.25) },  // morning ramp
        { duration: '5m', target: USERS },                      // busy period
        { duration: '10m', target: USERS },                     // hold
        { duration: '3m', target: 0 },                          // wind down
    ],
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<1500'],
        failed_requests: ['rate<0.01'],
    },
};

function signIn(n) {
    const page = http.get(`${BASE}/login`);
    const token = page.html().find('input[name=_token]').attr('value');
    const response = http.post(`${BASE}/login`, {
        _token: token,
        email: `person${n % 100000}@scale.test`,
        password: 'scale-test-password',
    });
    check(response, { 'signed in': (r) => r.status === 200 || r.status === 302 });
    return response;
}

export default function () {
    const n = __VU * 1000 + __ITER;
    signIn(n);

    group('dashboard', () => {
        const r = http.get(`${BASE}/dashboard/portfolio`, { headers: { 'X-Inertia': 'true' } });
        failures.add(r.status !== 200);
        check(r, { 'dashboard loads': (res) => res.status === 200 });
    });
    sleep(Math.random() * 3 + 2);

    group('projects', () => {
        const list = http.get(`${BASE}/projects`, { headers: { 'X-Inertia': 'true' } });
        failures.add(list.status !== 200);
        const map = http.get(`${BASE}/dashboard/map`, { headers: { 'X-Inertia': 'true' } });
        failures.add(map.status !== 200);
    });
    sleep(Math.random() * 4 + 2);

    group('lookup and report', () => {
        const lookup = http.get(`${BASE}/lookup/suppliers?q=Scale`, { headers: { Accept: 'application/json' } });
        failures.add(lookup.status !== 200);
        const report = http.get(`${BASE}/reports/cost-report`, { headers: { 'X-Inertia': 'true' } });
        failures.add(report.status !== 200);
    });
    sleep(Math.random() * 5 + 3);
}
