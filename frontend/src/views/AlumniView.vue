<template>
  <div class="p-4 md:p-6">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 md:mb-6 gap-3">
      <div>
        <h1 class="text-xl md:text-2xl font-bold text-gray-800">Alumni Directory</h1>
        <p class="text-sm md:text-base text-gray-600 mt-1">Connect with fellow LCBA alumni worldwide</p>
      </div>
      <div class="flex items-center space-x-2 md:space-x-4"></div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
      <div class="flex flex-col lg:flex-row gap-4">
        <!-- Search Bar -->
        <div class="w-full lg:w-80 xl:w-96">
          <div class="relative">
            <input 
              v-model="searchQuery"
              type="text" 
              placeholder="Search by name, batch, program, skills, or company..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
            </div>
          </div>
        </div>
        
        <!-- Filters -->
        <div class="flex flex-wrap gap-2">
          <select v-model="selectedProgram" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <option value="">All Programs</option>
            <option v-for="program in filterOptions.programs" :key="program" :value="program">
              {{ program }}
            </option>
          </select>

          <select v-model="selectedBatch" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <option value="">All Batches</option>
            <option v-for="batch in filterOptions.batches" :key="batch" :value="batch">
              {{ batch }}
            </option>
          </select>

          <select v-model="selectedLocation" class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            <option value="">All Locations</option>
            <option v-for="city in filterOptions.cities" :key="city" :value="city">
              {{ city }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column - Alumni Cards -->
      <div class="lg:col-span-2">
        <!-- View Toggle -->
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center space-x-4">
            <button 
              @click="viewMode = 'grid'"
              :class="[
                'px-3 py-1 rounded-lg text-sm font-medium',
                viewMode === 'grid' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-800'
              ]"
            >
              Grid View
            </button>
            <button 
              @click="viewMode = 'list'"
              :class="[
                'px-3 py-1 rounded-lg text-sm font-medium',
                viewMode === 'list' ? 'bg-blue-100 text-blue-700' : 'text-gray-600 hover:text-gray-800'
              ]"
            >
              List View
            </button>
          </div>
          <div class="text-sm text-gray-600">
            {{ filteredAlumni.length }} alumni found
            </div>
          </div>

        <!-- Alumni Cards -->
        <div v-if="loading" class="text-center py-12 text-gray-500">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p>Loading alumni...</p>
        </div>
        <div v-else-if="filteredAlumni.length === 0" class="text-center py-12 text-gray-500">
          <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
          </svg>
          <p>No alumni found matching your criteria</p>
          <p class="text-sm">Try adjusting your search or filters</p>
                </div>

        <!-- Grid View -->
        <div v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div 
            v-for="alumni in paginatedAlumni" 
            :key="alumni.id"
            class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow cursor-pointer"
            @click="viewProfile(alumni)"
          >
            <div class="flex items-center mb-4">
              <div class="w-16 h-16 rounded-full overflow-hidden bg-blue-500 flex items-center justify-center text-white font-bold text-xl mr-4">
                <img
                  v-if="alumni.profile_picture"
                  :src="getProfilePictureUrl(alumni.profile_picture)"
                  alt="Profile picture"
                  class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                  {{ alumni.first_name[0] }}{{ alumni.last_name[0] }}
                </div>
              </div>
                <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ alumni.full_name }}</h3>
                <p class="text-sm text-gray-600">{{ alumni.program }} {{ alumni.batch }}</p>
                <p class="text-sm text-gray-500">{{ alumni.headline || 'Alumni' }}</p>
                </div>
              </div>

            <div class="mb-4">
              <div class="flex flex-wrap gap-2 mb-2">
                <span 
                  v-for="skill in alumni.skills.slice(0, 3)" 
                  :key="skill"
                  class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"
                >
                  {{ skill }}
                </span>
                <span v-if="alumni.skills.length > 3" class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">
                  +{{ alumni.skills.length - 3 }} more
                </span>
              </div>
              <div class="flex items-center gap-2 mb-2">
                <span v-if="alumni.is_verified" class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">🏅 Verified</span>
                <span v-if="alumni.lcba_verification_status === 'verified' || alumni.is_lcba_employee_faculty" class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">🏢 LCBA Employee</span>
              </div>
            </div>

            <div class="flex space-x-2">
              <button 
                @click.stop="sendMessage(alumni)"
                class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700"
              >
                Message
              </button>
              <button 
                @click.stop="viewProfile(alumni)"
                class="flex-1 border border-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-50"
              >
                View Profile
              </button>
            </div>
                </div>
              </div>

        <!-- List View -->
        <div v-else class="space-y-4">
          <div 
            v-for="alumni in paginatedAlumni" 
            :key="alumni.id"
            class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow cursor-pointer"
            @click="viewProfile(alumni)"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <div class="w-12 h-12 rounded-full overflow-hidden bg-blue-500 flex items-center justify-center text-white font-bold mr-4">
                  <img
                    v-if="alumni.profile_picture"
                    :src="getProfilePictureUrl(alumni.profile_picture)"
                    alt="Profile picture"
                    class="w-full h-full object-cover"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center">
                    {{ alumni.first_name[0] }}{{ alumni.last_name[0] }}
                  </div>
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">{{ alumni.full_name }}</h3>
                  <p class="text-sm text-gray-600">{{ alumni.program }} {{ alumni.batch }} • {{ alumni.headline || 'Alumni' }}</p>
                  <div class="flex items-center gap-2 mt-1">
                    <span 
                      v-for="skill in alumni.skills.slice(0, 3)" 
                      :key="skill"
                      class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"
                    >
                      {{ skill }}
                    </span>
                  </div>
              </div>
            </div>

              <div class="flex items-center space-x-4">
                <div class="flex items-center gap-2">
                  <span v-if="alumni.is_verified" class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">🏅 Verified</span>
                  <span v-if="alumni.lcba_verification_status === 'verified' || alumni.is_lcba_employee_faculty" class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">🏢 LCBA Employee</span>
                </div>
                
                <div class="flex space-x-2">
                  <button 
                    @click.stop="sendMessage(alumni)"
                    class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700"
                  >
                    Message
                  </button>
                  <button 
                    @click.stop="viewProfile(alumni)"
                    class="border border-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-50"
                  >
                    View Profile
                  </button>
                </div>
              </div>
            </div>
                </div>
              </div>

        <!-- Pagination Controls -->
        <div v-if="totalPages > 1" class="mt-6 flex items-center justify-center gap-2">
          <button 
            @click="goToPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            &lt;
          </button>
          
          <button
            v-for="page in totalPages"
            :key="page"
            @click="goToPage(page)"
            :class="[
              'px-3 py-2 border rounded-lg min-w-[40px]',
              currentPage === page 
                ? 'bg-blue-600 text-white border-blue-600' 
                : 'border-gray-300 hover:bg-gray-50 text-gray-700'
            ]"
          >
            {{ page }}
          </button>
          
          <button 
            @click="goToPage(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            &gt;
          </button>
        </div>
            </div>

      <!-- Right Column - Suggested Connections -->
      <div class="space-y-6">
        <!-- Suggested Connections -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Suggested Connections</h3>
          <div class="space-y-4">
            <div 
              v-for="suggestion in suggestedConnections" 
              :key="suggestion.id"
              class="flex items-center justify-between"
            >
              <div class="flex items-center">
                <div class="w-10 h-10 rounded-full overflow-hidden bg-blue-500 flex items-center justify-center text-white font-bold text-sm mr-3">
                  <img
                    v-if="suggestion.profile_picture"
                    :src="getProfilePictureUrl(suggestion.profile_picture)"
                    alt="Profile picture"
                    class="w-full h-full object-cover"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center">
                    {{ suggestion.first_name[0] }}{{ suggestion.last_name[0] }}
                  </div>
                </div>
                <div>
                  <h4 class="font-medium text-gray-900">{{ suggestion.full_name }}</h4>
                  <p class="text-sm text-gray-600">{{ suggestion.program }} {{ suggestion.batch }} • {{ suggestion.headline }}</p>
                  <p class="text-xs text-gray-500">{{ suggestion.reason }}</p>
                </div>
              </div>
              <div class="flex space-x-1">
                <button 
                  @click="sendMessage(suggestion)"
                  class="text-blue-600 hover:text-blue-800 text-sm font-medium"
                >
                  Message
                </button>
              </div>
            </div>
              </div>
            </div>

        <!-- Recent Activity / Logs (Admin Only) -->
        <div v-if="isAdmin" class="bg-white rounded-lg shadow-md p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Activity Logs</h3>
            <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded-full">Last 30 days</span>
          </div>

          <div v-if="adminLogs.length > 0" class="space-y-3">
            <div
              v-for="(log, index) in paginatedLogs"
              :key="index"
              class="flex gap-3 py-3 border-b border-gray-100 last:border-0"
            >
              <!-- Icon -->
              <div :class="['w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-sm', getLogIconBg(log)]">
                {{ getLogIcon(log) }}
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <!-- Action sentence -->
                <p class="text-sm text-gray-800 leading-snug">{{ log.action }}</p>

                <!-- Actor + Target -->
                <div class="flex flex-wrap items-center gap-1 mt-1">
                  <span class="inline-flex items-center gap-1 text-xs bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded">
                    👤 {{ log.actor_name || 'Unknown' }}
                  </span>
                  <span v-if="log.target_user && log.target_user.name" class="inline-flex items-center gap-1 text-xs bg-purple-50 text-purple-700 px-1.5 py-0.5 rounded">
                    🎯 {{ log.target_user.name }}
                  </span>
                </div>

                <!-- Meta: time + IP -->
                <p class="text-xs text-gray-400 mt-1">
                  {{ formatActivityTime(log.created_at) }}
                  <span v-if="log.ip_address" class="ml-1">· {{ log.ip_address }}</span>
                </p>

                <!-- Details chips -->
                <div v-if="log.details && log.details.changed_fields && log.details.changed_fields.length > 0" class="flex flex-wrap gap-1 mt-1.5">
                  <span
                    v-for="field in log.details.changed_fields.slice(0, 4)"
                    :key="field"
                    class="text-xs bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded"
                  >{{ field }}</span>
                  <span v-if="log.details.changed_fields.length > 4" class="text-xs text-gray-400">+{{ log.details.changed_fields.length - 4 }} more</span>
                </div>
              </div>
            </div>

            <!-- Pagination -->
            <div v-if="totalLogPages > 1" class="flex items-center justify-center gap-2 mt-4 pt-3 border-t">
              <button
                @click="goToLogPage(currentLogPage - 1)"
                :disabled="currentLogPage === 1"
                class="px-2 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >&lt;</button>
              <span class="text-sm text-gray-600">{{ currentLogPage }} / {{ totalLogPages }}</span>
              <button
                @click="goToLogPage(currentLogPage + 1)"
                :disabled="currentLogPage === totalLogPages"
                class="px-2 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
              >&gt;</button>
            </div>
          </div>

          <div v-else class="text-center py-6">
            <p class="text-3xl mb-2">📋</p>
            <p class="text-sm text-gray-500">No activity logs in the last 30 days</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Profile Modal -->
    <div v-if="showProfileModal" class="fixed inset-0 bg-black/40 backdrop-blur-[1px] flex items-start md:items-center justify-center z-[80] px-4 pb-4 pt-20 md:pt-24 md:pl-64" @click.self="showProfileModal = false">
      <div class="bg-white rounded-lg w-full max-w-4xl max-h-[calc(100vh-6rem)] md:max-h-[calc(100vh-8rem)] overflow-y-auto border-2 border-black shadow-2xl">
        <div class="p-6">
          <!-- Modal Header -->
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center">
              <div class="w-20 h-20 rounded-full overflow-hidden bg-blue-500 flex items-center justify-center text-white font-bold text-2xl mr-4">
                <img
                  v-if="selectedAlumni.profile_picture"
                  :src="getProfilePictureUrl(selectedAlumni.profile_picture)"
                  alt="Profile picture"
                  class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                  {{ selectedAlumni.first_name[0] }}{{ selectedAlumni.last_name[0] }}
                </div>
              </div>
                <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ selectedAlumni.full_name }}</h2>
                <p class="text-gray-600">{{ selectedAlumni.program }} {{ selectedAlumni.batch }}</p>
                <p class="text-gray-500">{{ selectedAlumni.headline || 'Alumni' }}</p>
                </div>
              </div>
            <button @click="showProfileModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>

          <!-- Profile Tabs -->
          <div class="border-b border-gray-200 mb-6">
            <nav class="flex space-x-8">
              <button 
                v-for="tab in profileTabs" 
                :key="tab.key"
                @click="activeProfileTab = tab.key"
                :class="[
                  'py-2 px-1 border-b-2 font-medium text-sm',
                  activeProfileTab === tab.key 
                    ? 'border-blue-500 text-blue-600' 
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                ]"
              >
                {{ tab.label }}
              </button>
            </nav>
          </div>

          <!-- Tab Content -->
          <div class="mb-6">
            <!-- About Tab -->
            <div v-if="activeProfileTab === 'about'">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900 mb-4">About</h3>
                  <p class="text-gray-700 mb-4">{{ selectedAlumni.bio || 'No bio available' }}</p>

                  <h3 class="text-lg font-semibold text-gray-900 mb-4">Contact</h3>
                  <div class="space-y-3 mb-6">
                    <div>
                      <p class="text-sm font-medium text-gray-900">Email Address</p>
                      <div v-if="canViewField('email')" class="text-sm text-gray-600">
                        {{ selectedAlumni.email || 'Not provided' }}
                      </div>
                      <div v-else class="flex items-center text-sm text-gray-500">
                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-5a2 2 0 00-2-2h-1V7a5 5 0 00-10 0v2H6a2 2 0 00-2 2v5a2 2 0 002 2z"></path>
                        </svg>
                        Information is Private
                      </div>
                    </div>

                    <div>
                      <p class="text-sm font-medium text-gray-900">Phone Number</p>
                      <div v-if="canViewField('contact')" class="text-sm text-gray-600">
                        {{ selectedAlumni.phone_number || 'Not provided' }}
                      </div>
                      <div v-else class="flex items-center text-sm text-gray-500">
                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-5a2 2 0 00-2-2h-1V7a5 5 0 00-10 0v2H6a2 2 0 00-2 2v5a2 2 0 002 2z"></path>
                        </svg>
                        Information is Private
                      </div>
                    </div>
                  </div>
                  
                  <h3 class="text-lg font-semibold text-gray-900 mb-4">Personal</h3>
                  <div class="space-y-3 mb-6">
                    <div>
                      <p class="text-sm font-medium text-gray-900">Birthday</p>
                      <div v-if="canViewField('birthdate')" class="text-sm text-gray-600">
                        {{ formatBirthday(selectedAlumni.birthdate) || 'Not provided' }}
                      </div>
                      <div v-else class="flex items-center text-sm text-gray-500">
                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-5a2 2 0 00-2-2h-1V7a5 5 0 00-10 0v2H6a2 2 0 00-2 2v5a2 2 0 002 2z"></path>
                        </svg>
                        Information is Private
                      </div>
                    </div>
                    <div>
                      <p class="text-sm font-medium text-gray-900">Location</p>
                      <div v-if="canViewField('location')" class="text-sm text-gray-600">
                        {{ selectedAlumni.city }}{{ selectedAlumni.city && selectedAlumni.country ? ', ' : '' }}{{ selectedAlumni.country }}
                      </div>
                      <div v-else class="flex items-center text-sm text-gray-500">
                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-5a2 2 0 00-2-2h-1V7a5 5 0 00-10 0v2H6a2 2 0 00-2 2v5a2 2 0 002 2z"></path>
                        </svg>
                        Information is Private
                      </div>
                    </div>
                  </div>
                  
                  <h3 class="text-lg font-semibold text-gray-900 mb-4">Education</h3>
                  <div class="space-y-3">
                    <div class="border border-gray-200 rounded-lg p-4">
                      <div v-if="canViewField('education')">
                        <h4 class="font-medium text-gray-900">LCBA (Laguna College of Business and Arts)</h4>
                        <p class="text-sm text-gray-600">{{ selectedAlumni.program }} • {{ selectedAlumni.batch }}</p>
                      </div>
                      <div v-else class="flex items-center text-sm text-gray-500">
                        <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-5a2 2 0 00-2-2h-1V7a5 5 0 00-10 0v2H6a2 2 0 00-2 2v5a2 2 0 002 2z"></path>
                        </svg>
                        Information is Private
                      </div>
                    </div>
                  </div>
                </div>

                <div>
                  <h3 class="text-lg font-semibold text-gray-900 mb-4">Skills</h3>
                  <div class="flex flex-wrap gap-2 mb-6">
                    <span 
                      v-for="skill in selectedAlumni.skills" 
                      :key="skill"
                      class="bg-blue-100 text-blue-800 text-sm px-3 py-1 rounded-full"
                    >
                      {{ skill }}
                    </span>
                  </div>
                  
                  <h3 class="text-lg font-semibold text-gray-900 mb-4">Badges</h3>
                  <div class="flex flex-wrap gap-2">
                    <span v-if="selectedAlumni.is_verified" class="bg-yellow-100 text-yellow-800 text-sm px-3 py-1 rounded-full">🏅 Verified Alumni</span>
                  <span v-if="selectedAlumni.lcba_verification_status === 'verified' || selectedAlumni.is_lcba_employee_faculty" class="bg-green-100 text-green-800 text-sm px-3 py-1 rounded-full">🏢 LCBA Employee</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Experience Tab -->
            <div v-if="activeProfileTab === 'experience'">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Work Experience</h3>
              
              <!-- Only show if user has experience data -->
              <div v-if="hasCareerData || hasLocationData" class="space-y-4">
                <div class="border border-gray-200 rounded-lg p-4">
                  <div v-if="canViewField('career')">
                    <div class="flex items-start gap-3 mb-3">
                      <div class="w-12 h-12 bg-blue-100 rounded flex items-center justify-center text-blue-600 font-bold flex-shrink-0">
                        💼
                      </div>
                      <div class="flex-1">
                        <h4 class="font-medium text-gray-900">{{ selectedAlumni.current_job_title }}</h4>
                        <p v-if="selectedAlumni.industry" class="text-sm text-gray-600">{{ selectedAlumni.industry }}</p>
                        <p v-if="selectedAlumni.employment_status" class="text-xs text-gray-500 mt-1">
                          {{ formatEmploymentStatus(selectedAlumni.employment_status) }}
                        </p>
                      </div>
                    </div>
                  
                    <div v-if="selectedAlumni.years_of_experience" class="text-sm text-gray-600 mb-2">
                      <span class="font-medium">Experience:</span> {{ selectedAlumni.years_of_experience }} years
                    </div>
                  
                    <div v-if="selectedAlumni.employment_sector" class="text-sm text-gray-600 mb-2">
                      <span class="font-medium">Sector:</span> {{ formatEmploymentSector(selectedAlumni.employment_sector) }}
                    </div>

                    <div v-if="selectedAlumni.salary_range" class="text-sm text-gray-600 mb-2">
                      <span class="font-medium">Salary:</span> {{ selectedAlumni.salary_range }}
                    </div>
                  </div>
                  <div v-else class="flex items-center text-sm text-gray-500 mb-2">
                    <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-5a2 2 0 00-2-2h-1V7a5 5 0 00-10 0v2H6a2 2 0 00-2 2v5a2 2 0 002 2z"></path>
                    </svg>
                    Information is Private
                  </div>
                  
                  <div v-if="canViewField('location')" class="text-sm text-gray-600">
                    <span class="font-medium">Location:</span> {{ selectedAlumni.city }}{{ selectedAlumni.city && selectedAlumni.country ? ', ' : '' }}{{ selectedAlumni.country }}
                  </div>
                  <div v-else class="flex items-center text-sm text-gray-500">
                    <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-5a2 2 0 00-2-2h-1V7a5 5 0 00-10 0v2H6a2 2 0 00-2 2v5a2 2 0 002 2z"></path>
                    </svg>
                    Information is Private
                  </div>
                </div>
              </div>
              
              <div v-else class="text-center py-8 text-gray-500">
                <p class="text-sm">No work experience information available</p>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end space-x-4">
            <button @click="showProfileModal = false" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
              Close
            </button>
            <button @click="sendMessage(selectedAlumni)" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
              Send Message
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import axios from '../config/api'

const router = useRouter()

// Reactive data
const searchQuery = ref('')
const selectedProgram = ref('')
const selectedBatch = ref('')
const selectedCompany = ref('')
const selectedLocation = ref('')
const viewMode = ref('grid')
const showProfileModal = ref(false)
const selectedAlumni = ref({})
const activeProfileTab = ref('about')
const loading = ref(false)

// Pagination
const currentPage = ref(1)
const itemsPerPage = 10

// Admin Logs
const adminLogs = ref([])
const currentLogPage = ref(1)
const logsPerPage = 10
const authStore = useAuthStore()
const isAdmin = computed(() => authStore.isAdmin)
const isAdminOrStaff = computed(() => ['admin', 'staff'].includes(authStore.userRole))
const isViewerAlumni = computed(() => authStore.isAlumni)

// Filter options from database
const filterOptions = ref({
  programs: [],
  batches: [],
  cities: [],
  industries: []
})

// Fetch alumni from API
const alumni = ref([])
const userPosts = ref([])

const canViewField = (field) => {
  if (isAdminOrStaff.value) return true
  const privacy = selectedAlumni.value?.privacy_settings?.[field] || 'public'
  if (privacy === 'admin_only') return false
  if (privacy === 'alumni_only') return isViewerAlumni.value
  if (privacy === 'private') return false
  return true
}

const formatBirthday = (value) => {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
}

const hasCareerData = computed(() => {
  const a = selectedAlumni.value
  return Boolean(
    a?.current_job_title ||
    a?.industry ||
    a?.employment_status ||
    a?.years_of_experience ||
    a?.employment_sector ||
    a?.salary_range
  )
})

const hasLocationData = computed(() => {
  const a = selectedAlumni.value
  return Boolean(a?.city || a?.country)
})

const normalizeSkills = (skills) => {
  if (Array.isArray(skills)) return skills
  if (!skills) return []
  if (typeof skills === 'string') {
    try {
      const parsed = JSON.parse(skills)
      if (Array.isArray(parsed)) return parsed
      if (typeof parsed === 'string') {
        return parsed.split(',').map(s => s.trim()).filter(Boolean)
      }
      return []
    } catch (error) {
      return skills.split(',').map(s => s.trim()).filter(Boolean)
    }
  }
  return []
}

const getProfilePictureUrl = (profilePicture) => {
  if (!profilePicture) return ''
  if (profilePicture.startsWith('http://') || profilePicture.startsWith('https://')) return profilePicture
  const baseUrl = axios.defaults.baseURL || 'http://localhost:8000'
  if (profilePicture.startsWith('/uploads/')) return `${baseUrl}${profilePicture}`
  if (profilePicture.startsWith('uploads/')) return `${baseUrl}/${profilePicture}`
  return `${baseUrl}/uploads/profile_pictures/${profilePicture}`
}

const fetchAlumni = async () => {
  loading.value = true
  try {
    const params = {
      search: searchQuery.value || undefined,
      program: selectedProgram.value || undefined,
      batch: selectedBatch.value || undefined,
      role: 'alumni',
    }
    
    const response = await axios.get('/api/users', { params })
    if (response.data.success) {
      // Format the data for the view
      // Handle both paginated (response.data.data.data) and non-paginated (response.data.data) responses
      const usersData = Array.isArray(response.data.data) ? response.data.data : (response.data.data.data || [])
      
      alumni.value = usersData.map(user => ({
        id: user.id,
        first_name: user.first_name,
        last_name: user.last_name,
        full_name: `${user.first_name} ${user.last_name}`,
        email: user.email,
        profile_picture: user.profile_picture,
        program: user.program,
        batch: user.batch,
        headline: user.headline,
        company: user.current_job_title,
        bio: user.bio,
        birthdate: user.birthdate,
        city: user.city,
        country: user.country,
        location: user.location,
        phone_number: user.phone_number,
        contact_number: user.contact_number,
        address: user.address,
        industry: user.industry,
        current_job_title: user.current_job_title,
        employment_status: user.employment_status,
        years_of_experience: user.years_of_experience,
        employment_sector: user.employment_sector,
        salary_range: user.salary_range,
        skills: normalizeSkills(user.skills),
        lcba_verification_status: user.lcba_verification_status === 'rejected'
          ? 'unverified'
          : (user.lcba_verification_status || (user.is_lcba_employee_faculty ? 'verified' : '')),
        is_verified: !!user.is_verified,
        is_lcba_employee_faculty: !!user.is_lcba_employee_faculty,
        is_hiring: false,
        is_open_to_work: !user.current_job_title,
        privacy_settings: user.privacy_settings || {}
      }))
    }
  } catch (error) {
    console.error('Error fetching alumni:', error)
    alumni.value = []
  } finally {
    loading.value = false
  }
}

const fetchFilterOptions = async () => {
  try {
    const response = await axios.get('/api/users/filter-options')
    if (response?.data?.success) {
      filterOptions.value = response.data.data
    }
  } catch (error) {
    console.error('Error fetching filter options:', error)
  }
}

const suggestedConnections = ref([])

// Fetch suggested connections from API
const fetchSuggestedConnections = async () => {
  try {
    const authStore = useAuthStore()
    const currentUser = authStore.user
    
    if (!currentUser) return
    
    const response = await axios.get('/api/users', {
      params: {
        role: 'alumni',
        limit: 5,
        exclude: currentUser.id
      }
    })
    
    if (response.data.success) {
      const usersData = Array.isArray(response.data.data) ? response.data.data : (response.data.data.data || [])
      const filteredUsers = usersData.filter(user => user.id !== currentUser.id)
      const randomizedUsers = [...filteredUsers]
      
      for (let i = randomizedUsers.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1))
        const temp = randomizedUsers[i]
        randomizedUsers[i] = randomizedUsers[j]
        randomizedUsers[j] = temp
      }
      
      suggestedConnections.value = randomizedUsers.slice(0, 3).map(user => {
        let reason = ''
        
        // Determine reason for suggestion
        if (user.program === currentUser.program && user.batch === currentUser.batch) {
          reason = 'Same batch and program'
        } else if (user.program === currentUser.program) {
          reason = 'Same program'
        } else if (user.industry === currentUser.industry) {
          reason = 'Similar industry'
        } else if (user.city === currentUser.city) {
          reason = 'Same location'
        } else {
          reason = 'Alumni network'
        }
        
        return {
          id: user.id,
          first_name: user.first_name,
          last_name: user.last_name,
          full_name: `${user.first_name} ${user.last_name}`,
          profile_picture: user.profile_picture,
          program: user.program,
          batch: user.batch,
          headline: user.current_job_title || user.headline || 'LCBA Alumni',
          reason
        }
      })
    }
  } catch (error) {
    console.error('Error fetching suggested connections:', error)
  }
}

const profileTabs = [
  { key: 'about', label: 'About' },
  { key: 'experience', label: 'Experience' }
]

// Computed properties
const filteredAlumni = computed(() => {
  let filtered = alumni.value

  // Apply search filter
  if (searchQuery.value) {
    filtered = filtered.filter(alumni => 
      alumni.full_name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      alumni.program?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      alumni.batch?.includes(searchQuery.value) ||
      alumni.headline?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (Array.isArray(alumni.skills) && alumni.skills.some(skill => skill?.toLowerCase().includes(searchQuery.value.toLowerCase())))
    )
  }

  // Apply program filter
  if (selectedProgram.value) {
    filtered = filtered.filter(alumni => alumni.program === selectedProgram.value)
  }

  // Apply batch filter
  if (selectedBatch.value) {
    filtered = filtered.filter(alumni => alumni.batch === selectedBatch.value)
  }

  // Apply location filter
  if (selectedLocation.value) {
    filtered = filtered.filter(alumni => alumni.city === selectedLocation.value)
  }

  return filtered
})

const totalPages = computed(() => Math.ceil(filteredAlumni.value.length / itemsPerPage))

// Admin Logs Computed
const totalLogPages = computed(() => Math.ceil(adminLogs.value.length / logsPerPage))

const paginatedLogs = computed(() => {
  const start = (currentLogPage.value - 1) * logsPerPage
  const end = start + logsPerPage
  return adminLogs.value.slice(start, end)
})

const paginatedAlumni = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredAlumni.value.slice(start, end)
})

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const goToLogPage = (page) => {
  if (page >= 1 && page <= totalLogPages.value) {
    currentLogPage.value = page
  }
}

// Methods
const viewProfile = async (alumni) => {
  selectedAlumni.value = alumni
  showProfileModal.value = true
  activeProfileTab.value = 'about'
  
  // Fetch user's posts
  userPosts.value = []
  try {
    const response = await axios.get('/api/posts', {
      params: { user_id: alumni.id }
    })
    if (response.data.success) {
      userPosts.value = response.data.data.data || response.data.data || []
    }
  } catch (error) {
    console.error('Error loading user posts:', error)
    userPosts.value = []
  }
}

const sendMessage = async (alumni) => {
  // Navigate to Messages with intent; MessagesView will auto-open or create and send "Hi."
  router.push({ path: '/messages', query: { other_user_id: String(alumni.id), autohi: '1' } })
}


// Fetch Admin Logs
const fetchAdminLogs = async () => {
  if (!isAdmin.value) return
  
  try {
    const thirtyDaysAgo = new Date()
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30)
    
    const response = await axios.get('/api/admin-logs', {
      params: {
        from: thirtyDaysAgo.toISOString().split('T')[0],
        limit: 100
      }
    })
    
    if (response?.data?.success) {
      adminLogs.value = response.data.data.data || response.data.data || []
    }
  } catch (error) {
    console.error('Error fetching admin logs:', error)
    adminLogs.value = []
  }
}

const viewBatchAlumni = () => {
  const authStore = useAuthStore()
  const currentUser = authStore.user
  
  if (currentUser && currentUser.batch) {
    selectedBatch.value = currentUser.batch
    selectedProgram.value = ''
    searchQuery.value = ''
    fetchAlumni()
  } else {
    alert('Your batch information is not available')
  }
}

// Recent Activity Data
const fetchRecentActivity = async () => {
  // Removed - no longer needed
}

const formatActivityTime = (dateString) => {
  if (!dateString) return 'Recently'
  
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)
  
  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins} min${diffMins > 1 ? 's' : ''} ago`
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`
  if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`
  return date.toLocaleDateString()
}

const formatEmploymentStatus = (status) => {
  const statusMap = {
    'employed_full_time': 'Employed (Full-Time)',
    'employed_part_time': 'Employed (Part-Time)',
    'self_employed': 'Self-Employed',
    'in_study': 'In Further Study',
    'unemployed_looking': 'Seeking Opportunities',
    'unemployed_not_looking': 'Not Currently Seeking'
  }
  return statusMap[status] || status
}

const formatEmploymentSector = (sector) => {
  const sectorMap = {
    'public_government': 'Government/Public Sector',
    'private': 'Private Sector',
    'ngo_nonprofit': 'NGO/Non-Profit'
  }
  return sectorMap[sector] || sector
}

const formatPostDate = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  const now = new Date()
  const diff = now - date
  const seconds = Math.floor(diff / 1000)
  const minutes = Math.floor(seconds / 60)
  const hours = Math.floor(minutes / 60)
  const days = Math.floor(hours / 24)
  
  if (seconds < 60) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days < 7) return `${days}d ago`
  return date.toLocaleDateString()
}

const getLogIcon = (log) => {
  const type = log?.details?.action_type || log?.action || ''
  if (type.includes('login')) return '🔐'
  if (type.includes('logout')) return '🚪'
  if (type.includes('password') || type.includes('reset_password')) return '🔑'
  if (type.includes('profile_update') || type.includes('updated their profile')) return '✏️'
  if (type.includes('approve')) return '✅'
  if (type.includes('reject')) return '❌'
  if (type.includes('activate')) return '▶️'
  if (type.includes('deactivate')) return '⏸️'
  if (type.includes('create_event') || type.includes('event')) return '📅'
  if (type.includes('create_job') || type.includes('job')) return '💼'
  if (type.includes('import_alumni') || type.includes('csv')) return '📥'
  if (type.includes('verify') || type.includes('badge')) return '🏅'
  if (type.includes('role') || type.includes('admin')) return '🛡️'
  if (type.includes('rsvp')) return '📨'
  if (type.includes('remove') || type.includes('delete')) return '🗑️'
  return '📋'
}

const getLogIconBg = (log) => {
  const type = log?.details?.action_type || log?.action || ''
  if (type.includes('login') || type.includes('password') || type.includes('reset')) return 'bg-yellow-100'
  if (type.includes('approve') || type.includes('activate') || type.includes('verify')) return 'bg-green-100'
  if (type.includes('reject') || type.includes('deactivate') || type.includes('remove') || type.includes('delete')) return 'bg-red-100'
  if (type.includes('profile_update') || type.includes('updated their profile')) return 'bg-blue-100'
  if (type.includes('event') || type.includes('rsvp')) return 'bg-indigo-100'
  if (type.includes('job')) return 'bg-orange-100'
  if (type.includes('import') || type.includes('csv')) return 'bg-teal-100'
  if (type.includes('role') || type.includes('admin')) return 'bg-purple-100'
  return 'bg-gray-100'
}

onMounted(() => {
  fetchAlumni()
  fetchFilterOptions()
  fetchSuggestedConnections()
  if (isAdmin.value) {
    fetchAdminLogs()
  }
})

// Watch for filter changes and refetch
watch([searchQuery, selectedProgram, selectedBatch, selectedLocation], () => {
  currentPage.value = 1  // Reset to page 1 when filters change
})
</script>
