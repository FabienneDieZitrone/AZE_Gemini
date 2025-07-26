/**
 * Test setup file for Vitest
 * Configures testing environment and global imports
 */
import '@testing-library/jest-dom'

// Global test configuration for JSDOM environment
Object.defineProperty(window, 'ResizeObserver', {
  writable: true,
  value: class ResizeObserver {
    constructor(_cb: ResizeObserverCallback) {}
    observe() {}
    unobserve() {}
    disconnect() {}
  },
})

// Mock fetch for API tests  
Object.defineProperty(globalThis, 'fetch', {
  writable: true,
  value: () => Promise.resolve(new Response()),
})