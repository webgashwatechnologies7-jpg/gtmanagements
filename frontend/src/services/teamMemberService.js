import api from './api'

export const teamMemberService = {
  // TL: get members of my team
  getMyMembers() {
    return api.get('/teams/my-members')
  },

  // TL: get a single member detail
  getMyMemberDetail(userId) {
    return api.get(`/teams/my-members/${userId}`)
  }
}

