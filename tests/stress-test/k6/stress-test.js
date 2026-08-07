import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

// Custom metrics
const errorRate = new Rate('errors');

// Test configuration
export const options = {
  stages: [
    { duration: '30s', target: 20 },   // Ramp up to 20 users
    { duration: '1m', target: 20 },    // Stay at 20 users for 1 minute
    { duration: '30s', target: 50 },   // Ramp up to 50 users
    { duration: '2m', target: 50 },    // Stay at 50 users for 2 minutes
    { duration: '30s', target: 0 },    // Ramp down to 0
  ],
  thresholds: {
    http_req_duration: ['p(95)<500', 'p(99)<1000'], // 95% of requests under 500ms, 99% under 1000ms
    http_req_failed: ['rate<0.01'], // Less than 1% of requests failing
    errors: ['rate<0.05'], // Less than 5% errors
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://localhost:5005';
const API_BASE = `${BASE_URL}/api/v1`;

// Test data
const testUsers = [
  { email: 'owner1@staging.lafrenona3.test', password: 'Owner123!' },
  { email: 'waiter1@staging.lafrenona3.test', password: 'Staff123!' },
  { email: 'superadmin@staging.lafrenona3.test', password: 'SuperAdmin123!' },
];

const publicEndpoints = [
  `${API_BASE}/client/menu?restaurant=test-a`,
  `${API_BASE}/client/menu?restaurant=test-b`,
];

const loginEndpoint = `${API_BASE}/auth/login`;
const userEndpoint = `${API_BASE}/user`;
const staffEndpoint = `${API_BASE}/owner/staff`;
const analyticsEndpoint = `${API_BASE}/owner/analytics/summary`;

export default function () {
  const user = testUsers[Math.floor(Math.random() * testUsers.length)];
  const isPublic = Math.random() < 0.3; // 30% public requests

  if (isPublic) {
    // Public menu access
    const endpoint = publicEndpoints[Math.floor(Math.random() * publicEndpoints.length)];
    const res = http.get(endpoint);

    const success = check(res, {
      'public menu returns 200': (r) => r.status === 200,
      'public menu has categories': (r) => r.json('categories')?.length > 0,
      'public menu has products': (r) => r.json('products')?.length > 0,
    });

    errorRate.add(!success);
  } else {
    // Authenticated requests
    const loginRes = http.post(loginEndpoint, JSON.stringify({
      email: user.email,
      password: user.password,
    }), {
      headers: { 'Content-Type': 'application/json' },
    });

    const loginSuccess = check(loginRes, {
      'login returns 200': (r) => r.status === 200,
      'login returns token': (r) => r.json('access_token') !== undefined,
    });

    errorRate.add(!loginSuccess);

    if (loginSuccess) {
      const token = loginRes.json('access_token');
      const headers = {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      };

      // Random authenticated endpoint
      const endpoints = [
        { url: userEndpoint, name: 'user profile' },
        { url: staffEndpoint, name: 'staff list' },
        { url: analyticsEndpoint, name: 'analytics' },
      ];

      const selected = endpoints[Math.floor(Math.random() * endpoints.length)];
      const res = http.get(selected.url, headers);

      const success = check(res, {
        [`${selected.name} returns 200`]: (r) => r.status === 200,
      });

      errorRate.add(!success);
    }
  }

  sleep(Math.random() * 2 + 1); // Random think time 1-3 seconds
}

export function handleSummary(data) {
  return {
    'stdout': textSummary(data, { indent: ' ', enableColors: true }),
    './stress-test-results.json': JSON.stringify(data, null, 2),
  };
}

function textSummary(data, options) {
  const { indent = '', enableColors = false } = options;
  
  const checks = data.metrics || {};
  const lines = [
    `${indent}✓ Checks:`,
  ];

  for (const [name, metric] of Object.entries(checks)) {
    if (metric.values) {
      for (const [key, value] of Object.entries(metric.values)) {
        lines.push(`${indent}  ${name}.${key}: ${value.toFixed(2)}`);
      }
    }
  }

  return lines.join('\n');
}
