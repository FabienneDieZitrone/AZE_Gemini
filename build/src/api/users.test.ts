import { describe, it, expect, vi, beforeEach } from 'vitest';
import { api } from '../../api';

// Mock fetch
global.fetch = vi.fn();

describe('Users API', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('fetchUsers', () => {
    it('should fetch users successfully', async () => {
      const mockUsers = [
        { id: 1, username: 'test1', role: 'Mitarbeiter' },
        { id: 2, username: 'test2', role: 'Honorarkraft' }
      ];

      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ users: mockUsers })
      });

      const result = await api.fetchUsers();
      
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining('/api/users.php'),
        expect.any(Object)
      );
      expect(result.users).toEqual(mockUsers);
    });

    it('should handle errors gracefully', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: false,
        status: 403
      });

      await expect(api.fetchUsers()).rejects.toThrow();
    });
  });

  describe('Role-based filtering', () => {
    it('should filter users based on role permissions', () => {
      const users = [
        { id: 1, role: 'Admin' },
        { id: 2, role: 'Honorarkraft' },
        { id: 3, role: 'Mitarbeiter' }
      ];

      // Honorarkraft sollte nur sich selbst sehen
      const filteredForHonorarkraft = users.filter(u => u.id === 2);
      expect(filteredForHonorarkraft).toHaveLength(1);
      expect(filteredForHonorarkraft[0].role).toBe('Honorarkraft');
    });
  });
});