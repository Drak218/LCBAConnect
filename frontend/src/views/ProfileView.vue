<template>
  <div class="p-4 md:p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 md:mb-6 gap-3">
      <div>
        <h1 class="text-xl md:text-2xl font-bold text-gray-800">My Profile</h1>
        <p class="text-sm md:text-base text-gray-600 mt-1">Manage your information and privacy settings</p>
      </div>
      <div class="flex items-center gap-2">
        <button @click="resetProfile" :disabled="saving" class="border border-gray-300 text-gray-700 px-3 md:px-4 py-2 rounded-lg hover:bg-gray-50 disabled:opacity-50 text-sm md:text-base">
          Reset
        </button>
        <button @click="saveProfile" :disabled="saving" class="bg-blue-600 text-white px-3 md:px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm md:text-base">
          {{ saving ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
      <div class="lg:col-span-2 space-y-6">
        <!-- Profile Picture -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Profile Picture</h2>
          <div class="flex flex-col md:flex-row items-start gap-6">
            <!-- Current Profile Picture Display -->
            <div class="relative flex flex-col items-start w-36 md:w-40 flex-shrink-0">
              <div class="w-32 h-32 rounded-full overflow-hidden border-4 border-gray-200 bg-gray-100">
                <img 
                  v-if="profilePictureUrl" 
                  :src="profilePictureUrl" 
                  alt="Profile Picture"
                  class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-400 to-blue-600 text-white text-4xl font-bold">
                  {{ getInitials() }}
                </div>
              </div>
              <div class="mt-3 w-full">
                <p class="text-sm font-semibold text-gray-900">Badges</p>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                  <span v-if="authStore.user?.is_verified" class="bg-yellow-100 text-yellow-800 text-sm px-3 py-1 rounded-full">🏅 Verified Alumni</span>
                  <span v-if="form.lcba_verification_status === 'verified'" class="bg-green-100 text-green-800 text-sm px-3 py-1 rounded-full">🏢 LCBA Employee</span>
                </div>
              </div>
            </div>
            
            <!-- Upload Controls -->
            <div class="flex-1 min-w-0">
              <div class="mb-3 flex flex-wrap items-center gap-2">
                <input 
                  ref="fileInput"
                  type="file" 
                  accept="image/*"
                  @change="handleFileSelect"
                  class="hidden"
                />
                <button 
                  @click="$refs.fileInput.click()"
                  :disabled="uploadingPicture || deletingPicture"
                  class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                >
                  {{ uploadingPicture ? 'Uploading...' : (profilePictureUrl ? 'Change Picture' : 'Upload Picture') }}
                </button>
                <button
                  v-if="profilePictureUrl"
                  @click="deleteProfilePicture"
                  :disabled="uploadingPicture || deletingPicture"
                  class="border border-red-300 text-red-700 px-4 py-2 rounded-lg hover:bg-red-50 disabled:opacity-50 text-sm font-medium"
                >
                  {{ deletingPicture ? 'Removing...' : 'Remove Picture' }}
                </button>
              </div>
              
              <p class="text-sm text-gray-600 mb-1">
                Accepted formats: JPG, PNG, GIF, WebP
              </p>
              <p class="text-sm text-gray-600">
                Maximum file size: 5MB
              </p>

              <div v-if="uploadingPicture && uploadProgress > 0" class="mt-3">
                <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                  <div class="h-full bg-blue-600" :style="{ width: uploadProgress + '%' }"></div>
                </div>
                <p class="text-xs text-gray-600 mt-1">{{ uploadProgress }}%</p>
              </div>
              
              <!-- Upload Error Message -->
              <div v-if="uploadError" class="mt-3 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                {{ uploadError }}
              </div>
              
              <!-- Upload Success Message -->
              <div v-if="uploadSuccess" class="mt-3 p-3 bg-green-50 border border-green-200 rounded text-sm text-green-700">
                Profile picture uploaded successfully!
              </div>
            </div>
          </div>
        </div>

        <!-- Basic Information -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
              <input v-model="form.first_name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Middle Name <span class="text-gray-400 text-xs">(Optional)</span></label>
              <input v-model="form.middle_name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
              <input v-model="form.last_name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Suffix <span class="text-gray-400 text-xs">(Optional)</span></label>
              <input v-model="form.suffix" type="text" placeholder="Jr., Sr., III, etc." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Birthday <span class="text-gray-400 text-xs">(Optional)</span></label>
              <input v-model="form.birthdate" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Program</label>
              <input v-model="form.program" type="text" readonly class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Headline</label>
              <input v-model="form.headline" type="text" placeholder="e.g., Junior Developer at TechStart" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
          </div>
        </div>

        <!-- Contact & Socials -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Contact & Socials</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Email Address
                <span class="text-xs text-gray-500">(Privacy controlled in settings below)</span>
              </label>
              <input 
                v-model="form.email" 
                type="email" 
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
              <p class="text-xs text-gray-500 mt-1">
                This is your login email. Visibility is controlled by your privacy settings.
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
              <input
                v-model="form.phone_number"
                type="tel"
                inputmode="numeric"
                pattern="[0-9]*"
                @input="sanitizePhoneNumber"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">LinkedIn Profile URL</label>
              <input v-model="form.linkedin_url" type="url" placeholder="https://linkedin.com/in/yourprofile" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Personal Portfolio/Website URL</label>
              <input v-model="form.portfolio_url" type="url" placeholder="https://yourportfolio.com" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Education History</h2>
            <button @click="showEducationModal = true" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
              + Add Education
            </button>
          </div>

          <div v-if="form.program || form.batch" class="mb-4 p-4 bg-blue-50 border-l-4 border-blue-500 rounded">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                  <span class="text-sm font-medium text-gray-900">🎓 LCBA - {{ form.program || 'Program' }}</span>
                  <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">LCBA</span>
                </div>
                <p class="text-xs text-gray-600">Graduated: {{ form.batch || 'Year not specified' }}</p>
              </div>
            </div>
          </div>

          <!-- Education History Timeline -->
          <div v-if="educationHistory.length > 0" class="space-y-3">
            <div v-for="edu in educationHistory" :key="edu.id" class="p-4 border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="text-sm font-medium text-gray-900">{{ getLevelLabel(edu.level) }} - {{ edu.school_name }}</span>
                    <span v-if="edu.is_lcba" class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">LCBA</span>
                  </div>
                  <p v-if="edu.program" class="text-xs text-gray-700">{{ edu.program }}</p>
                  <p v-if="edu.year_graduated" class="text-xs text-gray-600">Graduated: {{ edu.year_graduated }}</p>
                  <p v-if="edu.awards" class="text-xs text-gray-500 mt-1">🏆 {{ edu.awards }}</p>
                </div>
                <div class="flex items-center gap-2">
                  <button @click="editEducation(edu)" class="text-blue-600 hover:text-blue-800 text-xs">
                    Edit
                  </button>
                  <button @click="deleteEducation(edu.id)" class="text-red-600 hover:text-red-800 text-xs">
                    Delete
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="text-center py-8 text-gray-500">
            <p class="text-sm">No additional education history added yet.</p>
            <p class="text-xs mt-1">Click "Add Education" to start building your educational timeline.</p>
          </div>

          <!-- Highest Attainment -->
          <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Highest Educational Attainment</label>
            <input
              :value="getAttainmentLabel(form.highest_educational_attainment)"
              type="text"
              readonly
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-700"
            />
            <p class="text-xs text-gray-500 mt-1">When you save a new Education History entry, the list updates and the watcher immediately sets form.highest_educational_attainment to the highest-ranked level.</p>
          </div>
        </div>

        <!-- Career -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Career</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Current Job Title</label>
              <input v-model="form.current_job_title" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Industry</label>
              <div class="relative">
                <input
                  v-model="form.industry"
                  type="text"
                  placeholder="Search industry"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  @focus="industryDropdownOpen = true"
                  @input="onIndustryInput"
                  @blur="closeIndustryDropdown"
                />
                <div v-if="industryDropdownOpen" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                  <button
                    v-for="industry in filteredIndustryOptions"
                    :key="industry"
                    type="button"
                    class="w-full text-left px-3 py-2 hover:bg-gray-50"
                    @mousedown.prevent="selectIndustry(industry)"
                  >
                    <div class="text-sm text-gray-900">{{ industry }}</div>
                  </button>
                  <div v-if="filteredIndustryOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                    No matching industries.
                  </div>
                </div>
              </div>
            </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
            <div class="relative">
              <input
                v-model="form.city"
                type="text"
                placeholder="Search city"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                @focus="cityDropdownOpen = true"
                @input="onCityInput"
                @blur="closeCityDropdown"
              />
              <div v-if="cityDropdownOpen" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <button
                  v-for="city in filteredCityOptions"
                  :key="city"
                  type="button"
                  class="w-full text-left px-3 py-2 hover:bg-gray-50"
                  @mousedown.prevent="selectCity(city)"
                >
                  <div class="text-sm text-gray-900">{{ city }}</div>
                </button>
                <div v-if="filteredCityOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                  No matching cities.
                </div>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Municipality</label>
            <div class="relative">
              <input
                v-model="form.municipality"
                type="text"
                placeholder="Search municipality"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                @focus="municipalityDropdownOpen = true"
                @input="onMunicipalityInput"
                @blur="closeMunicipalityDropdown"
              />
              <div v-if="municipalityDropdownOpen" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <button
                  v-for="mun in filteredMunicipalityOptions"
                  :key="mun"
                  type="button"
                  class="w-full text-left px-3 py-2 hover:bg-gray-50"
                  @mousedown.prevent="selectMunicipality(mun)"
                >
                  <div class="text-sm text-gray-900">{{ mun }}</div>
                </button>
                <div v-if="filteredMunicipalityOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                  No matching municipalities.
                </div>
              </div>
            </div>
          </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
              <div class="relative">
                <input
                  v-model="form.country"
                  type="text"
                  placeholder="Search country"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  @focus="countryDropdownOpen = true"
                  @input="onCountryInput"
                  @blur="closeCountryDropdown"
                />
                <div v-if="countryDropdownOpen" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                  <button
                    v-for="country in filteredCountryOptions"
                    :key="country"
                    type="button"
                    class="w-full text-left px-3 py-2 hover:bg-gray-50"
                    @mousedown.prevent="selectCountry(country)"
                  >
                    <div class="text-sm text-gray-900">{{ country }}</div>
                  </button>
                  <div v-if="filteredCountryOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                    No matching countries.
                  </div>
                </div>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Employment Status</label>
              <select v-model="form.employment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Select status</option>
                <option value="employed_full_time">Employed (Full-Time)</option>
                <option value="employed_part_time">Employed (Part-Time)</option>
                <option value="self_employed">Self-Employed / Freelance</option>
                <option value="in_study">In Further Study (e.g., Master's)</option>
                <option value="unemployed_looking">Unemployed (Actively Looking)</option>
                <option value="unemployed_not_looking">Unemployed (Not Looking)</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Employment Sector <span class="text-gray-400 text-xs">(Optional)</span></label>
              <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2">
                  <input type="radio" v-model="form.employment_sector" value="" class="border-gray-300" />
                  <span class="text-sm">None</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="radio" v-model="form.employment_sector" value="public_government" class="border-gray-300" />
                  <span class="text-sm">Government/Public</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="radio" v-model="form.employment_sector" value="private" class="border-gray-300" />
                  <span class="text-sm">Private</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="radio" v-model="form.employment_sector" value="ngo_nonprofit" class="border-gray-300" />
                  <span class="text-sm">NGO/Non-Profit</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Years of Experience</label>
              <input v-model.number="form.years_of_experience" type="number" min="0" max="100" step="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Monthly Salary Range (PHP)</label>
              <div class="space-y-3">
                <div class="grid grid-cols-2 gap-2 text-sm">
                  <div>
                    <label class="block text-xs text-gray-500 mb-1">Min</label>
                    <input v-model.number="salaryMin" type="number" :min="salaryRangeMin" :max="salaryMax" :step="salaryStep" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                  </div>
                  <div>
                    <label class="block text-xs text-gray-500 mb-1">Max</label>
                    <input v-model.number="salaryMax" type="number" :min="salaryMin" :step="salaryStep" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
                  </div>
                </div>
                <div class="flex items-center justify-between text-sm text-gray-600">
                  <span>{{ formatSalaryValue(salaryMin) }}</span>
                  <span>{{ formatSalaryValue(salaryMax) }}</span>
                </div>
                <div class="space-y-2">
                  <input v-model.number="salaryMin" type="range" :min="salaryRangeMin" :max="salaryVisualMax" :step="salaryStep" class="w-full" />
                  <input v-model.number="salaryMax" type="range" :min="salaryRangeMin" :max="salaryVisualMax" :step="salaryStep" class="w-full" />
                </div>
              </div>
            </div>
            <div class="md:col-span-2">
              <label class="flex items-center gap-2">
                <input type="checkbox" v-model="form.is_lcba_employee_faculty" class="rounded border-gray-300" />
                <span class="text-sm font-medium text-gray-700">I am a current/former LCBA Employee or Faculty</span>
              </label>
              
              <div v-if="form.is_lcba_employee_faculty" class="mt-3 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <div class="mb-3">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Employee/Faculty ID Photo</label>
                  <input
                    ref="employeeIdInput"
                    type="file"
                    accept="image/*"
                    @change="onEmployeeIdFileChange"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  />
                  <p class="text-xs text-gray-600 mt-1">Upload a clear photo of your LCBA Employee/Faculty ID for verification</p>
                  <p v-if="lcbaEmployeeIdFile" class="text-xs text-gray-600 mt-2">
                    Selected file: {{ lcbaEmployeeIdFile.name }}
                  </p>
                  <p v-else-if="form.lcba_employee_id" class="text-xs text-gray-600 mt-2">
                    Uploaded ID: On file
                  </p>
                </div>

                <div class="flex items-center gap-3">
                  <button
                    type="button"
                    @click="uploadEmployeeIdPhoto"
                    :disabled="saving || !lcbaEmployeeIdFile"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm"
                  >
                    Upload ID Photo
                  </button>
                  <span v-if="!lcbaEmployeeIdFile" class="text-xs text-gray-600">Select a file to upload</span>
                </div>
                
                <div v-if="form.lcba_verification_status" class="flex items-center gap-2">
                  <span v-if="form.lcba_verification_status === 'verified' || form.is_lcba_employee_faculty" class="inline-flex items-center gap-1 bg-green-100 text-green-800 text-sm px-3 py-1 rounded-full">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    LCBA Employee
                  </span>
                  <span v-else-if="form.lcba_verification_status === 'pending'" class="inline-flex items-center gap-1 bg-yellow-100 text-yellow-800 text-sm px-3 py-1 rounded-full">
                    ⏳ Verification Pending
                  </span>
                  <span v-else-if="form.lcba_verification_status === 'unverified'" class="inline-flex items-center gap-1 bg-red-100 text-red-800 text-sm px-3 py-1 rounded-full">
                    ❌ Unverified
                  </span>
                </div>
              </div>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Skills</label>
              <div class="border border-gray-300 rounded-lg p-2 min-h-[42px] focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-transparent">
                <div class="flex flex-wrap gap-2 mb-2">
                  <span
                    v-for="(skill, index) in form.skills"
                    :key="index"
                    class="inline-flex items-center gap-1 bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm"
                  >
                    {{ skill }}
                    <button
                      @click="removeSkill(index)"
                      class="text-blue-600 hover:text-blue-800 focus:outline-none"
                      type="button"
                    >
                      ×
                    </button>
                  </span>
                </div>
                <div class="relative">
                  <input
                    v-model="skillInput"
                    @keydown.enter.prevent="selectSkillFromInput"
                    @focus="onSkillInput"
                    @input="searchSkills"
                    @blur="closeSkillDropdown"
                    type="text"
                    placeholder="Search and select from list"
                    class="w-full px-2 py-1 border-0 focus:ring-0 focus:outline-none"
                  />
                  <div v-if="skillDropdownOpen" class="absolute z-10 mt-2 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto">
                    <button
                      v-for="suggestion in skillSuggestions"
                      :key="suggestion.id"
                      @mousedown.prevent="selectSkill(suggestion.name)"
                      type="button"
                      class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center justify-between"
                    >
                      <span>{{ suggestion.name }}</span>
                      <span class="text-xs text-gray-500">{{ suggestion.category }}</span>
                    </button>
                    <div v-if="skillSuggestions.length === 0" class="px-4 py-2 text-sm text-gray-500">
                      No matching skills.
                    </div>
                  </div>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">Skills are standardized for better matching</p>
            </div>
          </div>
        </div>

        <!-- Career Preferences -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Career Preferences</h2>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Work Setup</label>
              <div class="flex flex-wrap gap-3">
                <label class="flex items-center gap-2">
                  <input type="checkbox" v-model="form.work_setup_preferences" value="on_site" class="rounded border-gray-300" />
                  <span class="text-sm">On-Site</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="checkbox" v-model="form.work_setup_preferences" value="hybrid" class="rounded border-gray-300" />
                  <span class="text-sm">Hybrid</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="checkbox" v-model="form.work_setup_preferences" value="remote" class="rounded border-gray-300" />
                  <span class="text-sm">Remote</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Employment Type</label>
              <div class="flex flex-wrap gap-3">
                <label class="flex items-center gap-2">
                  <input type="checkbox" v-model="form.employment_type_preferences" value="full_time" class="rounded border-gray-300" />
                  <span class="text-sm">Full-Time</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="checkbox" v-model="form.employment_type_preferences" value="part_time" class="rounded border-gray-300" />
                  <span class="text-sm">Part-Time</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="checkbox" v-model="form.employment_type_preferences" value="contract" class="rounded border-gray-300" />
                  <span class="text-sm">Contract</span>
                </label>
                <label class="flex items-center gap-2">
                  <input type="checkbox" v-model="form.employment_type_preferences" value="internship" class="rounded border-gray-300" />
                  <span class="text-sm">Internship</span>
                </label>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Industries of Interest</label>
              <div class="border border-gray-300 rounded-lg p-2 min-h-[42px] focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-transparent">
                <div class="flex flex-wrap gap-2 mb-2">
                  <span
                    v-for="(industry, index) in form.industries_of_interest"
                    :key="index"
                    class="inline-flex items-center gap-1 bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm"
                  >
                    {{ industry }}
                    <button
                      @click="removeIndustry(index)"
                      class="text-green-600 hover:text-green-800 focus:outline-none"
                      type="button"
                    >
                      ×
                    </button>
                  </span>
                </div>
                <div class="relative">
                  <input
                    v-model="industryInput"
                    @keydown.enter.prevent="addIndustry"
                    @focus="interestDropdownOpen = true"
                    @input="onInterestInput"
                    @blur="closeInterestDropdown"
                    type="text"
                    placeholder="Search and press Enter to add"
                    class="w-full px-2 py-1 border-0 focus:ring-0 focus:outline-none"
                  />
                  <div v-if="interestDropdownOpen" class="absolute z-10 mt-2 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    <button
                      v-for="industry in filteredInterestIndustryOptions"
                      :key="industry"
                      type="button"
                      class="w-full text-left px-3 py-2 hover:bg-gray-50"
                      @mousedown.prevent="selectInterestIndustry(industry)"
                    >
                      <div class="text-sm text-gray-900">{{ industry }}</div>
                    </button>
                    <div v-if="filteredInterestIndustryOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                      No matching industries.
                    </div>
                  </div>
                </div>
              </div>
              <p class="text-xs text-gray-500 mt-1">List industries you're interested in working in</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Privacy Settings -->
      <div class="space-y-6">
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <span class="mr-2">🔒</span>Privacy Settings
          </h2>
          <p class="text-sm text-gray-600 mb-4">Control who can see your profile information in compliance with the Data Privacy Act.</p>
          
          <div class="space-y-4">
            <!-- Email Visibility -->
            <div class="border-b border-gray-200 pb-3">
              <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-800">Email Address</span>
                <select v-model="privacySettings.email" class="px-3 py-1 border border-gray-300 rounded text-sm">
                  <option value="public">Public</option>
                  <option value="admin_only">Private (Admin Only)</option>
                </select>
              </div>
              <p class="text-xs text-gray-500">Your login email: {{ form.email }}</p>
            </div>

            <!-- Contact Information -->
            <div class="border-b border-gray-200 pb-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-800">Phone Number</span>
                <select v-model="privacySettings.contact" class="px-3 py-1 border border-gray-300 rounded text-sm">
                  <option value="public">Public</option>
                  <option value="admin_only">Private (Admin Only)</option>
                </select>
              </div>
            </div>

            <!-- Birthday -->
            <div class="border-b border-gray-200 pb-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-800">Birthday</span>
                <select v-model="privacySettings.birthdate" class="px-3 py-1 border border-gray-300 rounded text-sm">
                  <option value="public">Public</option>
                  <option value="admin_only">Private (Admin Only)</option>
                </select>
              </div>
              <p class="text-xs text-gray-500">Affects Birthday Corner visibility</p>
            </div>

            <!-- Education History -->
            <div class="border-b border-gray-200 pb-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-800">Education History</span>
                <select v-model="privacySettings.education" class="px-3 py-1 border border-gray-300 rounded text-sm">
                  <option value="public">Public</option>
                  <option value="admin_only">Private (Admin Only)</option>
                </select>
              </div>
            </div>

            <!-- Career Information -->
            <div class="border-b border-gray-200 pb-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-800">Current Job & Salary</span>
                <select v-model="privacySettings.career" class="px-3 py-1 border border-gray-300 rounded text-sm">
                  <option value="public">Public</option>
                  <option value="admin_only">Private (Admin Only)</option>
                </select>
              </div>
            </div>

            <!-- Location -->
            <div class="pb-3">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-800">Location (City & Country)</span>
                <select v-model="privacySettings.location" class="px-3 py-1 border border-gray-300 rounded text-sm">
                  <option value="public">Public</option>
                  <option value="admin_only">Private (Admin Only)</option>
                </select>
              </div>
            </div>
          </div>

          <div class="mt-4 p-3 bg-blue-50 border-l-4 border-blue-400 text-sm">
            <p class="text-gray-700">
              <strong>Note:</strong> Administrators can always view your information for verification and compliance purposes. Your basic information (name, LCBA program) is always visible to alumni.
            </p>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">
            <span class="mr-2">🔐</span>Security
          </h2>
          <p class="text-sm text-gray-600 mb-4">Update your account password.</p>
          <button @click="openChangePasswordModal" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">
            Change Password
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Education Modal -->
  <div v-if="showEducationModal" class="fixed inset-0 bg-black/40 backdrop-blur-[1px] flex items-start md:items-center justify-center z-[80] px-4 pb-4 pt-20 md:pt-24 md:pl-64" @click.self="closeEducationModal">
    <div class="bg-white rounded-lg shadow-xl border-2 border-black p-6 w-full max-w-md max-h-[calc(100vh-6rem)] md:max-h-[calc(100vh-8rem)] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-900">
          {{ editingEducation ? 'Edit Education' : 'Add Education' }}
        </h2>
        <button @click="closeEducationModal" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <form @submit.prevent="saveEducation">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Education Level <span class="text-red-500">*</span></label>
            <select v-model="educationForm.level" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
              <option value="">Select level</option>
              <option value="elementary">Elementary</option>
              <option value="high_school">High School</option>
              <option value="senior_high">Senior High School</option>
              <option value="college">College/Bachelor's</option>
              <option value="masters">Master's Degree</option>
              <option value="doctorate">Doctorate/PhD</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">School <span class="text-red-500">*</span></label>
            <input v-model="educationForm.school_name" required type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="Search school">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Program <span class="text-red-500">*</span></label>
            <div class="relative">
              <input
                v-model="educationForm.program"
                required
                type="text"
                placeholder="Search program"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                @focus="programDropdownOpen = true"
                @input="onProgramInput"
                @blur="closeProgramDropdown"
              />
              <div v-if="programDropdownOpen" class="absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                <button
                  v-for="prog in filteredProgramOptions"
                  :key="prog"
                  type="button"
                  class="w-full text-left px-3 py-2 hover:bg-gray-50"
                  @mousedown.prevent="selectProgram(prog)"
                >
                  <div class="text-sm text-gray-900">{{ prog }}</div>
                </button>
                <div v-if="filteredProgramOptions.length === 0" class="px-3 py-2 text-sm text-gray-500">
                  No matching programs.
                </div>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Year Graduated</label>
            <input v-model="educationForm.year_graduated" required type="number" min="1950" :max="new Date().getFullYear() + 10" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="e.g., 2020">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Awards/Honors (Optional)</label>
            <textarea v-model="educationForm.awards" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 resize-none" placeholder="e.g., Cum Laude, Dean's Lister"></textarea>
          </div>

          <div class="flex items-center">
            <span 
              v-if="isLcbaSchool(educationForm.school_name)" 
              class="inline-flex items-center text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded"
            >
              LCBA
            </span>
          </div>
        </div>

        <div class="mt-6 flex justify-end space-x-3">
          <button type="button" @click="closeEducationModal" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
            Cancel
          </button>
          <button type="submit" :disabled="submittingEducation" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
            {{ submittingEducation ? 'Saving...' : 'Save' }}
          </button>
        </div>
      </form>
    </div>
  </div>

  <div v-if="showChangePasswordModal" class="fixed inset-0 bg-black/40 backdrop-blur-[1px] flex items-start md:items-center justify-center z-[80] px-4 pb-4 pt-20 md:pt-24 md:pl-64" @click.self="closeChangePasswordModal">
    <div class="bg-white rounded-lg shadow-xl border-2 border-black p-6 w-full max-w-md max-h-[calc(100vh-6rem)] md:max-h-[calc(100vh-8rem)] overflow-y-auto">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-semibold text-gray-900">Change Password</h2>
        <button @click="closeChangePasswordModal" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>

      <div v-if="changePasswordError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-700">
        {{ changePasswordError }}
      </div>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
          <input v-model="changePasswordForm.current_password" type="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
          <input v-model="changePasswordForm.password" type="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
          <input v-model="changePasswordForm.password_confirmation" type="password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" />
        </div>
      </div>

      <div class="mt-6 flex justify-end space-x-3">
        <button type="button" @click="closeChangePasswordModal" class="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
          Cancel
        </button>
        <button type="button" @click="submitChangePassword" :disabled="changingPassword" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
          {{ changingPassword ? 'Updating...' : 'Update Password' }}
        </button>
      </div>
    </div>
  </div>

  <datalist id="city-options">
    <option v-for="city in availableCityOptions" :key="city" :value="city"></option>
  </datalist>
</template>

<script setup>
import { ref, onMounted, watch, computed, onBeforeUnmount } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import axios from '../config/api'
import { useAuthStore } from '../stores/auth'
import { useToast } from '../composables/useToast'

const toast = useToast()
const authStore = useAuthStore()
const saving = ref(false)
const skillInput = ref('')
const skillDropdownOpen = ref(false)
const municipalityDropdownOpen = ref(false)
const industryInput = ref('')
const industryDropdownOpen = ref(false)
const countryDropdownOpen = ref(false)
const interestDropdownOpen = ref(false)
const skillSuggestions = ref([])
let skillSearchTimeout = null
const salaryMin = ref(0)
const salaryMax = ref(0)
const salaryRangeMin = 0
const salaryRangeMax = 200000
const salaryStep = 1000
const salaryVisualMax = computed(() => {
  const max = Number(salaryMax.value || 0)
  return Math.max(salaryRangeMax, max)
})
const filterOptions = ref({
  cities: [],
  industries: [],
  programs: [],
  batches: []
})
// Profile Picture Upload
const fileInput = ref(null)
const employeeIdInput = ref(null)
const lcbaEmployeeIdFile = ref(null)
const uploadingPicture = ref(false)
const deletingPicture = ref(false)
const uploadProgress = ref(0)
const uploadError = ref('')
const uploadSuccess = ref(false)
const profilePictureUrl = ref('')
const initialForm = ref(null)
const initialPrivacySettings = ref(null)

// Education History
const educationHistory = ref([])
const showEducationModal = ref(false)
const editingEducation = ref(null)
const submittingEducation = ref(false)
const educationForm = ref({
  level: '',
  school_name: '',
  program: '',
  year_graduated: '',
  awards: '',
  is_lcba: false
})

const form = ref({
  first_name: '',
  middle_name: '',
  last_name: '',
  suffix: '',
  birthdate: '',
  email: '',
  headline: '',
  // Contact & Socials
  phone_number: '',
  linkedin_url: '',
  portfolio_url: '',
  // Education
  program: '',
  batch: '',
  highest_educational_attainment: '',
  // Career
  current_job_title: '',
  industry: '',
  city: '',
  municipality: '',
  country: '',
  employment_status: '',
  employment_sector: '',
  years_of_experience: null,
  salary_range: '',
  skills: [],
  // Career Preferences
  work_setup_preferences: [],
  employment_type_preferences: [],
  industries_of_interest: [],
  // LCBA Employee/Faculty
  is_lcba_employee_faculty: false,
  lcba_employee_id: '',
  lcba_verification_status: ''
})

const privacySettings = ref({
  email: 'public',
  contact: 'public',
  birthdate: 'public',
  education: 'public',
  career: 'public',
  location: 'public'
})

const showChangePasswordModal = ref(false)
const changingPassword = ref(false)
const changePasswordError = ref('')
const changePasswordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: ''
})

const staticIndustryOptions = [
  'Accounting','Advertising','Aerospace','Agriculture','Automotive','Banking','Biotechnology','Chemical','Civil Engineering',
  'Construction','Consulting','Consumer Goods','Design','E-commerce','Education','Energy','Entertainment','Environmental Services',
  'Fashion','Finance','Food & Beverage','Gaming','Government','Healthcare','Hospitality','Human Resources','Information Technology',
  'Insurance','Internet','Investment Management','Legal Services','Logistics & Supply Chain','Manufacturing','Marketing & PR',
  'Media & Communications','Mining & Metals','Nonprofit','Oil & Gas','Pharmaceuticals','Printing','Real Estate',
  'Renewables & Environment','Research','Retail','Security & Investigations','Telecommunications','Transportation',
  'Travel & Tourism','Utilities','Wholesale'
]
const availableIndustryOptions = computed(() => {
  const apiOptions = Array.isArray(filterOptions.value.industries) ? filterOptions.value.industries : []
  const base = [...staticIndustryOptions, ...apiOptions]
  const unique = Array.from(new Set(base))
  const addValue = (value) => {
    if (value && !unique.includes(value)) {
      unique.push(value)
    }
  }
  addValue(form.value.industry)
  if (Array.isArray(form.value.industries_of_interest)) {
    form.value.industries_of_interest.forEach(addValue)
  }
  return unique
})

const cityDropdownOpen = ref(false)
const staticCityOptions = [
  'Alaminos','Angeles','Antipolo','Bacolod','Bacoor','Bago','Baguio','Bais','Balanga','Baliwag','Batac','Batangas City',
  'Bayawan','Baybay','Bayugan','Biñan','Bislig','Bogo','Borongan','Butuan','Cabadbaran','Cabanatuan','Cabuyao','Cadiz',
  'Cagayan de Oro','Calaca','Calamba','Calapan','Calbayog','Caloocan','Candon','Canlaon','Carcar','Carmona','Catbalogan',
  'Cauayan','Cavite City','Cebu City','Cotabato City','Dagupan','Danao','Dapitan','Dasmariñas','Davao City','Digos',
  'Dipolog','Dumaguete','El Salvador','Escalante','Gapan','General Santos','General Trias','Gingoog','Guihulngan',
  'Himamaylan','Ilagan','Iligan','Iloilo City','Imus','Iriga','Isabela City','Kabankalan','Kidapawan','Koronadal',
  'La Carlota','Lamitan','Laoag','Lapu-Lapu City','Las Piñas','Legazpi','Ligao','Lipa','Lucena','Maasin','Mabalacat',
  'Makati','Malabon','Malaybalay','Malolos','Mandaluyong','Mandaue','Manila','Marawi','Marikina','Masbate City','Mati',
  'Meycauayan','Muñoz','Muntinlupa','Naga (Camarines Sur)','Naga (Cebu)','Navotas','Olongapo','Ormoc','Oroquieta',
  'Ozamiz','Pagadian','Palayan','Panabo','Parañaque','Pasay','Pasig','Passi','Puerto Princesa','Quezon City','Roxas',
  'Sagay','Samal','San Carlos (Negros Occidental)','San Carlos (Pangasinan)','San Fernando (La Union)','San Fernando (Pampanga)',
  'San Jose (Nueva Ecija)','San Jose (Occidental Mindoro)','San Jose del Monte','San Juan','San Pablo','San Pedro',
  'Santa Rosa','Santiago','Silay','Sipalay','Sorsogon City','Surigao City','Tabaco','Tabuk','Tacloban','Tacurong','Tagaytay',
  'Tagbilaran','Taguig','Tagum','Talisay (Cebu)','Talisay (Negros Occidental)','Tanauan','Tandag','Tangub','Tanjay',
  'Tarlac City','Tayabas','Toledo','Trece Martires','Tuguegarao','Urdaneta','Valencia','Valenzuela','Victorias','Vigan',
  'Zamboanga City'
]
const availableCityOptions = computed(() => {
  const apiOptions = Array.isArray(filterOptions.value.cities) ? filterOptions.value.cities : []
  const base = [...staticCityOptions, ...apiOptions]
  const unique = Array.from(new Set(base))
  if (form.value.city && !unique.includes(form.value.city)) {
    unique.push(form.value.city)
  }
  return unique
})

const programDropdownOpen = ref(false)
const staticProgramOptions = [
  'AB Political Science',
  'Bachelor of Elementary Education',
  'Bachelor of Secondary Education (Majors in English, Mathematics, Social Studies, Science, Filipino)',
  'BS Computer Science',
  'BS Psychology',
  'BS Computer Engineering',
  'BS Accountancy',
  'BS Business Administration (Majors in Human Resources Management, Marketing Management)',
  'BS Entrepreneurship in Culinary Arts',
  'Master of Arts in Education Major in Guidance and Counseling',
  'Master in Business Administration',
  'Master of Science in Psychology',
  'Master of Arts in Education Major in Administration and Supervision',
  'Master of Arts in Education Major in English',
  'Master of Arts in Education Major in Filipino',
  'Master of Arts in Education Major in Social Studies',
  'Master in Management major in Public Administration',
  'Senior High School Graduate',
  'High School Graduate',
  'Elementary Graduate'
]
const availableProgramOptions = computed(() => {
  const apiOptions = Array.isArray(filterOptions.value.programs) ? filterOptions.value.programs : []
  const base = [...staticProgramOptions, ...apiOptions]
  const unique = Array.from(new Set(base))
  if (educationForm.value.program && !unique.includes(educationForm.value.program)) {
    unique.push(educationForm.value.program)
  }
  return unique
})
const programSearchTerm = computed(() => (educationForm.value.program || '').toLowerCase().trim())
const filteredProgramOptions = computed(() => {
  const options = availableProgramOptions.value
  if (!programSearchTerm.value) return options
  return options.filter((option) => option.toLowerCase().includes(programSearchTerm.value))
})

const countriesList = ref([
  'Afghanistan','Albania','Algeria','Andorra','Angola','Antigua & Deps','Argentina','Armenia','Australia','Austria','Azerbaijan',
  'Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize','Benin','Bhutan','Bolivia','Bosnia Herzegovina','Botswana',
  'Brazil','Brunei','Bulgaria','Burkina','Burundi','Cambodia','Cameroon','Canada','Cape Verde','Central African Rep','Chad','Chile',
  'China','Colombia','Comoros','Congo','Congo {Democratic Rep}','Costa Rica','Croatia','Cuba','Cyprus','Czech Republic','Denmark',
  'Djibouti','Dominica','Dominican Republic','East Timor','Ecuador','Egypt','El Salvador','Equatorial Guinea','Eritrea','Estonia',
  'Ethiopia','Fiji','Finland','France','Gabon','Gambia','Georgia','Germany','Ghana','Greece','Grenada','Guatemala','Guinea',
  'Guinea-Bissau','Guyana','Haiti','Honduras','Hungary','Iceland','India','Indonesia','Iran','Iraq','Ireland {Republic}','Israel',
  'Italy','Ivory Coast','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kiribati','Korea North','Korea South','Kosovo','Kuwait',
  'Kyrgyzstan','Laos','Latvia','Lebanon','Lesotho','Liberia','Libya','Liechtenstein','Lithuania','Luxembourg','Macedonia',
  'Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Marshall Islands','Mauritania','Mauritius','Mexico','Micronesia',
  'Moldova','Monaco','Mongolia','Montenegro','Morocco','Mozambique','Myanmar, {Burma}','Namibia','Nauru','Nepal','Netherlands',
  'New Zealand','Nicaragua','Niger','Nigeria','Norway','Oman','Pakistan','Palau','Panama','Papua New Guinea','Paraguay','Peru',
  'Philippines','Poland','Portugal','Qatar','Romania','Russian Federation','Rwanda','St Kitts & Nevis','St Lucia',
  'Saint Vincent & the Grenadines','Samoa','San Marino','Sao Tome & Principe','Saudi Arabia','Senegal','Serbia','Seychelles',
  'Sierra Leone','Singapore','Slovakia','Slovenia','Solomon Islands','Somalia','South Africa','South Sudan','Spain','Sri Lanka',
  'Sudan','Suriname','Swaziland','Sweden','Switzerland','Syria','Taiwan','Tajikistan','Tanzania','Thailand','Togo','Tonga',
  'Trinidad & Tobago','Tunisia','Turkey','Turkmenistan','Tuvalu','Uganda','Ukraine','United Arab Emirates','United Kingdom',
  'United States','Uruguay','Uzbekistan','Vanuatu','Vatican City','Venezuela','Vietnam','Yemen','Zambia','Zimbabwe'
])
const availableCountryOptions = computed(() => {
  const base = Array.isArray(countriesList.value) && countriesList.value.length ? countriesList.value : ['Philippines']
  const combined = [...base]
  if (form.value.country && !combined.includes(form.value.country)) {
    combined.push(form.value.country)
  }
  return combined
})

const industrySearchTerm = computed(() => (form.value.industry || '').toLowerCase().trim())
const countrySearchTerm = computed(() => (form.value.country || '').toLowerCase().trim())
const interestSearchTerm = computed(() => industryInput.value.toLowerCase().trim())

const filteredIndustryOptions = computed(() => {
  const options = availableIndustryOptions.value
  if (!industrySearchTerm.value) return options
  return options.filter((option) => option.toLowerCase().includes(industrySearchTerm.value))
})

const filteredCountryOptions = computed(() => {
  const options = availableCountryOptions.value
  if (!countrySearchTerm.value) return options
  return options.filter((option) => option.toLowerCase().includes(countrySearchTerm.value))
})

const filteredInterestIndustryOptions = computed(() => {
  const options = availableIndustryOptions.value.filter(
    (option) => !form.value.industries_of_interest.includes(option)
  )
  if (!interestSearchTerm.value) return options
  return options.filter((option) => option.toLowerCase().includes(interestSearchTerm.value))
})

const citySearchTerm = computed(() => (form.value.city || '').toLowerCase().trim())
const filteredCityOptions = computed(() => {
  const options = availableCityOptions.value
  if (!citySearchTerm.value) return options
  return options.filter((option) => option.toLowerCase().includes(citySearchTerm.value))
})
const onCityInput = () => {
  cityDropdownOpen.value = true
}
const selectCity = (city) => {
  form.value.city = city
  cityDropdownOpen.value = false
}
const closeCityDropdown = () => {
  setTimeout(() => {
    cityDropdownOpen.value = false
  }, 150)
}

const staticMunicipalityOptions = [
  // Laguna
  'Alaminos','Bay','Biñan','Cabuyao','Calauan','Cavinti','Famy','Kalayaan','Liliw',
  'Los Baños','Luisiana','Lumban','Mabitac','Magdalena','Majayjay','Nagcarlan',
  'Paete','Pagsanjan','Pakil','Pangil','Pila','Rizal','San Pablo','San Pedro',
  'Santa Cruz','Santa Maria','Santa Rosa','Siniloan','Victoria',
  // Batangas
  'Agoncillo','Alitagtag','Balayan','Balete','Batangas City','Bauan','Calaca',
  'Calatagan','Cuenca','Ibaan','Laurel','Lemery','Lian','Lobo','Mabini','Malvar',
  'Mataas na Kahoy','Nasugbu','Padre Garcia','Rosario','San Jose','San Juan',
  'San Luis','San Nicolas','San Pascual','Santa Teresita','Santo Tomas','Taal',
  'Talisay','Taysan','Tingloy','Tuy',
  // Quezon Province
  'Agdangan','Alabat','Atimonan','Buenavista','Burdeos','Calauag','Candelaria',
  'Catanauan','Dolores','General Luna','General Nakar','Guinayangan','Gumaca',
  'Infanta','Jomalig','Lopez','Lucban','Lucena City','Macalelon','Mauban',
  'Mulanay','Padre Burgos','Pagbilao','Panukulan','Patnanungan','Perez',
  'Pitogo','Plaridel','Polillo','Quezon','Real','Sampaloc','San Andres',
  'San Antonio','San Francisco','San Narciso','Sariaya','Tagkawayan','Tayabas',
  'Tiaong','Unisan',
  // Cavite
  'Alfonso','Amadeo','Bacoor','Carmona','Cavite City','Dasmariñas','General Emilio Aguinaldo',
  'General Mariano Alvarez','General Trias','Imus','Indang','Kawit','Magallanes',
  'Maragondon','Mendez','Naic','Noveleta','Rosario','Silang','Tagaytay','Tanza',
  'Ternate','Trece Martires',
  // Rizal
  'Angono','Antipolo','Baras','Binangonan','Cainta','Cardona','Jala-Jala',
  'Morong','Pililla','Rodriguez','San Mateo','Tanay','Taytay','Teresa',
  // Metro Manila cities as municipalities
  'Caloocan','Las Piñas','Makati','Malabon','Mandaluyong','Manila','Marikina',
  'Muntinlupa','Navotas','Parañaque','Pasay','Pasig','Pateros','Quezon City',
  'San Juan','Taguig','Valenzuela',
  // Cebu
  'Alcantara','Alcoy','Alegria','Aloguinsan','Argao','Asturias','Badian','Balamban',
  'Bantayan','Barili','Bogo','Boljoon','Borbon','Carmen','Catmon','Compostela',
  'Consolacion','Cordova','Daanbantayan','Dalaguete','Danao','Dumanjug','Ginatilan',
  'Lapu-Lapu City','Liloan','Madridejos','Malabuyoc','Mandaue','Medellin','Minglanilla',
  'Moalboal','Naga','Oslob','Pilar','Pinamungajan','Poro','Ronda','Samboan',
  'San Fernando','San Francisco','San Remigio','Santa Fe','Santander','Sibonga',
  'Sogod','Tabogon','Tabuelan','Talisay','Toledo','Tuburan','Tudela',
  // Davao Region
  'Baguio City (Benguet)','Bansalan','Carmen','Davao City','Digos','Don Marcelino',
  'Hagonoy','Jose Abad Santos','Kiblawan','Magsaysay','Malalag','Malita',
  'Matanao','Mati','Padada','Santa Cruz','Sta. Maria','Sulop',
]

const municipalitySearchTerm = computed(() => (form.value.municipality || '').toLowerCase().trim())
const filteredMunicipalityOptions = computed(() => {
  const options = staticMunicipalityOptions
  if (!municipalitySearchTerm.value) return options
  return options.filter((option) => option.toLowerCase().includes(municipalitySearchTerm.value))
})

const onMunicipalityInput = () => {
  municipalityDropdownOpen.value = true
}
const selectMunicipality = (mun) => {
  form.value.municipality = mun
  municipalityDropdownOpen.value = false
}
const closeMunicipalityDropdown = () => {
  setTimeout(() => {
    municipalityDropdownOpen.value = false
  }, 150)
}
const isLcbaSchool = (name) => {
  if (!name) return false
  const n = String(name).trim().toLowerCase()
  return n === 'lcba' || n === 'laguna college of business and arts'
}

const hasUnsavedChanges = computed(() => {
  if (!initialForm.value || !initialPrivacySettings.value) return false
  const formChanged = JSON.stringify(form.value) !== JSON.stringify(initialForm.value)
  const privacyChanged = JSON.stringify(privacySettings.value) !== JSON.stringify(initialPrivacySettings.value)
  return formChanged || privacyChanged
})

const loadProfile = async () => {
  try {
    const me = authStore.user
    if (me) {
      // Format birthdate from ISO to YYYY-MM-DD for date input
      let formattedBirthdate = ''
      if (me.birthdate) {
        try {
          const dateObj = new Date(me.birthdate)
          if (!isNaN(dateObj.getTime())) {
            const year = dateObj.getFullYear()
            const month = String(dateObj.getMonth() + 1).padStart(2, '0')
            const day = String(dateObj.getDate()).padStart(2, '0')
            formattedBirthdate = `${year}-${month}-${day}`
          }
        } catch (e) {
          console.error('Error parsing birthdate:', e)
        }
      }
      
      form.value = {
        first_name: me.first_name || '',
        middle_name: me.middle_name || '',
        last_name: me.last_name || '',
        suffix: me.suffix || '',
        birthdate: formattedBirthdate,
        email: me.email || '',
        headline: me.headline || '',
        phone_number: me.phone_number || '',
        linkedin_url: me.linkedin_url || '',
        portfolio_url: me.portfolio_url || '',
        program: me.program || '',
        batch: me.batch || '',
        highest_educational_attainment: me.highest_educational_attainment || '',
        current_job_title: me.current_job_title || '',
        industry: me.industry || '',
        city: me.city || '',
        municipality: me.municipality || '',
        country: me.country || '',
        employment_status: me.employment_status || '',
        employment_sector: me.employment_sector || '',
        years_of_experience: me.years_of_experience !== null && me.years_of_experience !== undefined ? me.years_of_experience : null,
        salary_range: me.salary_range || '',
        skills: me.skills || [],
        work_setup_preferences: me.work_setup_preferences || [],
        employment_type_preferences: me.employment_type_preferences || [],
        industries_of_interest: me.industries_of_interest || [],
        is_lcba_employee_faculty: me.is_lcba_employee_faculty || false,
        lcba_employee_id: me.lcba_employee_id || '',
        lcba_verification_status: me.lcba_verification_status === 'rejected' ? 'unverified' : (me.lcba_verification_status || '')
      }
      if (me.privacy_settings) {
        privacySettings.value = { ...privacySettings.value, ...me.privacy_settings }
      }
      Object.keys(privacySettings.value).forEach((key) => {
        if (privacySettings.value[key] === 'connections_only' || privacySettings.value[key] === 'alumni_only') {
          privacySettings.value[key] = 'public'
        }
      })
      if (me.profile_picture) {
        profilePictureUrl.value = resolveProfilePictureUrl(me.profile_picture)
      } else {
        profilePictureUrl.value = ''
      }
      syncSalaryFromForm()
      initialForm.value = JSON.parse(JSON.stringify(form.value))
      initialPrivacySettings.value = JSON.parse(JSON.stringify(privacySettings.value))
    }
  } catch (error) {
    console.error('Error loading profile:', error)
  }
}

const loadFilterOptions = async () => {
  try {
    const response = await axios.get('/api/users/filter-options')
    if (response?.data?.success && response.data.data) {
      filterOptions.value = {
        ...filterOptions.value,
        ...response.data.data
      }
    }
  } catch (error) {
    console.error('Error loading filter options:', error)
  }
}

const parseSalaryRange = (value) => {
  if (!value) return { min: 0, max: 0 }
  if (typeof value === 'number') return { min: value, max: value }
  const numbers = String(value).match(/\d+/g)
  if (!numbers || !numbers.length) return { min: 0, max: 0 }
  const parsed = numbers.map((num) => Number(num)).filter((num) => !Number.isNaN(num))
  if (parsed.length === 1) return { min: parsed[0], max: parsed[0] }
  const min = Math.min(parsed[0], parsed[1])
  const max = Math.max(parsed[0], parsed[1])
  return { min, max }
}

const syncSalaryFromForm = () => {
  const { min, max } = parseSalaryRange(form.value.salary_range)
  salaryMin.value = min
  salaryMax.value = max
}

const formatSalaryValue = (value) => `₱${Number(value || 0).toLocaleString()}`

const handleBeforeUnload = (event) => {
  if (!hasUnsavedChanges.value) return
  event.preventDefault()
  event.returnValue = ''
}

watch(
  () => form.value.salary_range,
  (value) => {
    const { min, max } = parseSalaryRange(value)
    if (min !== salaryMin.value) {
      salaryMin.value = min
    }
    if (max !== salaryMax.value) {
      salaryMax.value = max
    }
  }
)

watch(
  [salaryMin, salaryMax],
  ([minValue, maxValue]) => {
    let min = Number(minValue || 0)
    let max = Number(maxValue || 0)
    if (!form.value.salary_range && min === 0 && max === 0) {
      return
    }
    if (min > max) {
      const temp = min
      min = max
      max = temp
    }
    if (min !== salaryMin.value) {
      salaryMin.value = min
    }
    if (max !== salaryMax.value) {
      salaryMax.value = max
    }
    const formatted = `${min}-${max}`
    if (form.value.salary_range !== formatted) {
      form.value.salary_range = formatted
    }
  }
)

const openChangePasswordModal = () => {
  changePasswordError.value = ''
  showChangePasswordModal.value = true
}

const closeChangePasswordModal = (force = false) => {
  if (changingPassword.value && !force) return
  showChangePasswordModal.value = false
  changePasswordError.value = ''
  changePasswordForm.value = {
    current_password: '',
    password: '',
    password_confirmation: ''
  }
}

const submitChangePassword = async () => {
  changePasswordError.value = ''
  if (changePasswordForm.value.password !== changePasswordForm.value.password_confirmation) {
    changePasswordError.value = 'New passwords do not match.'
    return
  }
  if (!changePasswordForm.value.current_password || !changePasswordForm.value.password) {
    changePasswordError.value = 'Please fill in all password fields.'
    return
  }
  changingPassword.value = true
  try {
    const response = await axios.put('/api/user/password', {
      current_password: changePasswordForm.value.current_password,
      password: changePasswordForm.value.password,
      password_confirmation: changePasswordForm.value.password_confirmation
    })
    if (response.data?.message) {
      toast.success(response.data.message, 'Success')
    } else {
      toast.success('Password updated successfully', 'Success')
    }
    closeChangePasswordModal(true)
  } catch (error) {
    const message = error.response?.data?.message
    if (message) {
      changePasswordError.value = message
    } else if (error.response?.data?.errors) {
      const errors = Object.values(error.response.data.errors).flat().join(' ')
      changePasswordError.value = errors || 'Failed to update password.'
    } else {
      changePasswordError.value = 'Failed to update password.'
    }
  } finally {
    changingPassword.value = false
  }
}

const resetProfile = () => {
  if (initialForm.value) {
    form.value = JSON.parse(JSON.stringify(initialForm.value))
  }
  if (initialPrivacySettings.value) {
    privacySettings.value = JSON.parse(JSON.stringify(initialPrivacySettings.value))
  }
  lcbaEmployeeIdFile.value = null
  if (employeeIdInput.value) {
    employeeIdInput.value.value = ''
  }
}

const onEmployeeIdFileChange = (event) => {
  const file = event.target.files?.[0]
  lcbaEmployeeIdFile.value = file || null
}

const uploadEmployeeIdPhoto = async () => {
  if (!lcbaEmployeeIdFile.value) {
    toast.warning('Please select an Employee/Faculty ID photo first', 'Notice')
    return
  }
  await saveProfile()
}

const getInitials = () => {
  const firstName = form.value.first_name || authStore.user?.first_name || ''
  const lastName = form.value.last_name || authStore.user?.last_name || ''
  return (firstName.charAt(0) + lastName.charAt(0)).toUpperCase() || 'U'
}

const resolveProfilePictureUrl = (profilePicture) => {
  if (!profilePicture) return ''
  if (profilePicture.startsWith('http://') || profilePicture.startsWith('https://')) return profilePicture
  const baseUrl = axios.defaults.baseURL || 'http://localhost:8000'
  if (profilePicture.startsWith('/uploads/')) return `${baseUrl}${profilePicture}`
  if (profilePicture.startsWith('uploads/')) return `${baseUrl}/${profilePicture}`
  return `${baseUrl}/uploads/profile_pictures/${profilePicture}`
}

const handleFileSelect = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  uploadError.value = ''
  uploadSuccess.value = false
  uploadProgress.value = 0

  const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']
  if (!validTypes.includes(file.type)) {
    uploadError.value = 'Please select a valid image file (JPG, PNG, GIF, or WebP)'
    return
  }

  const maxSize = 5 * 1024 * 1024
  if (file.size > maxSize) {
    uploadError.value = 'File size must be less than 5MB'
    return
  }

  uploadingPicture.value = true

  try {
    const formData = new FormData()
    formData.append('profile_picture', file)

    const response = await axios.post('/api/user/profile-picture', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      },
      onUploadProgress: (progressEvent) => {
        if (!progressEvent.total) return
        uploadProgress.value = Math.round((progressEvent.loaded / progressEvent.total) * 100)
      }
    })

    const updatedUser = response.data.user || authStore.user
    if (updatedUser) {
      authStore.user = updatedUser
      sessionStorage.setItem('user_data', JSON.stringify(updatedUser))
    }

    profilePictureUrl.value = resolveProfilePictureUrl(updatedUser?.profile_picture || response.data.profile_picture_url || '')

    uploadSuccess.value = true
    toast.success('Profile picture uploaded successfully!')

    setTimeout(() => {
      uploadSuccess.value = false
    }, 3000)
  } catch (error) {
    console.error('Error uploading profile picture:', error)
    uploadError.value = error.response?.data?.message || 'Failed to upload profile picture. Please try again.'
  } finally {
    uploadingPicture.value = false
    event.target.value = ''
    setTimeout(() => {
      uploadProgress.value = 0
    }, 300)
  }
}

const deleteProfilePicture = async () => {
  if (!profilePictureUrl.value) return
  deletingPicture.value = true
  uploadError.value = ''
  uploadSuccess.value = false

  try {
    const response = await axios.delete('/api/user/profile-picture')
    const updatedUser = response.data.user || authStore.user
    if (updatedUser) {
      authStore.user = updatedUser
      sessionStorage.setItem('user_data', JSON.stringify(updatedUser))
    }
    profilePictureUrl.value = ''
    toast.success('Profile picture removed.')
  } catch (error) {
    console.error('Error deleting profile picture:', error)
    uploadError.value = error.response?.data?.message || 'Failed to remove profile picture. Please try again.'
  } finally {
    deletingPicture.value = false
  }
}

const searchSkills = async () => {
  if (!skillInput.value || skillInput.value.length < 2) {
    skillSuggestions.value = []
    return
  }

  clearTimeout(skillSearchTimeout)
  skillSearchTimeout = setTimeout(async () => {
    try {
      const response = await axios.get('/api/skills', {
        params: { q: skillInput.value, limit: 10 }
      })
      if (response.data.success) {
        skillSuggestions.value = response.data.data.filter(
          skill => !form.value.skills.includes(skill.name)
        )
      }
    } catch (error) {
      console.error('Error searching skills:', error)
    }
  }, 300)
}

const onSkillInput = () => {
  skillDropdownOpen.value = true
}

const closeSkillDropdown = () => {
  setTimeout(() => {
    skillDropdownOpen.value = false
  }, 150)
}

const selectSkillFromInput = () => {
  const query = skillInput.value.trim()
  if (!query) return
  const lowerQuery = query.toLowerCase()
  const match = skillSuggestions.value.find((skill) => skill?.name?.toLowerCase() === lowerQuery)
  if (match) {
    selectSkill(match.name)
  } else {
    selectSkill(query)
  }
}

const selectSkill = (skillName) => {
  if (!form.value.skills.includes(skillName)) {
    form.value.skills.push(skillName)
  }
  skillInput.value = ''
  skillSuggestions.value = []
  skillDropdownOpen.value = false
}

const removeSkill = (index) => {
  form.value.skills.splice(index, 1)
}

const onIndustryInput = () => {
  industryDropdownOpen.value = true
}

const selectIndustry = (industry) => {
  form.value.industry = industry
  industryDropdownOpen.value = false
}

const closeIndustryDropdown = () => {
  setTimeout(() => {
    industryDropdownOpen.value = false
  }, 150)
}

const onProgramInput = () => {
  programDropdownOpen.value = true
}
const selectProgram = (prog) => {
  educationForm.value.program = prog
  programDropdownOpen.value = false
}
const closeProgramDropdown = () => {
  setTimeout(() => {
    programDropdownOpen.value = false
  }, 150)
}

const onCountryInput = () => {
  countryDropdownOpen.value = true
}

const selectCountry = (country) => {
  form.value.country = country
  countryDropdownOpen.value = false
}

const closeCountryDropdown = () => {
  setTimeout(() => {
    countryDropdownOpen.value = false
  }, 150)
}

const onInterestInput = () => {
  interestDropdownOpen.value = true
}

const selectInterestIndustry = (industry) => {
  if (industry && !form.value.industries_of_interest.includes(industry)) {
    form.value.industries_of_interest.push(industry)
  }
  industryInput.value = ''
  interestDropdownOpen.value = false
}

const closeInterestDropdown = () => {
  setTimeout(() => {
    interestDropdownOpen.value = false
  }, 150)
}

const addIndustry = () => {
  const industry = industryInput.value.trim()
  if (industry && !form.value.industries_of_interest.includes(industry)) {
    form.value.industries_of_interest.push(industry)
    industryInput.value = ''
  }
  interestDropdownOpen.value = false
}

const sanitizePhoneNumber = () => {
  form.value.phone_number = (form.value.phone_number || '').replace(/[^0-9]/g, '')
}

const removeIndustry = (index) => {
  form.value.industries_of_interest.splice(index, 1)
}

const saveProfile = async () => {
  saving.value = true
  try {
    const payload = {
      ...form.value,
      privacy_settings: privacySettings.value
    }
    
    // Don't send email if it's the same (field is disabled)
    if (payload.email === authStore.user?.email) {
      delete payload.email
    }
    
    // Ensure birthdate format is correct (YYYY-MM-DD)
    if (payload.birthdate && typeof payload.birthdate === 'string') {
      // If it's already in YYYY-MM-DD format, keep it
      // Otherwise, format it properly
      const dateObj = new Date(payload.birthdate)
      if (!isNaN(dateObj.getTime())) {
        const year = dateObj.getFullYear()
        const month = String(dateObj.getMonth() + 1).padStart(2, '0')
        const day = String(dateObj.getDate()).padStart(2, '0')
        payload.birthdate = `${year}-${month}-${day}`
      }
    }
    
    // Clean up numeric fields - convert empty strings to null or remove them
    if (payload.years_of_experience === '' || payload.years_of_experience === null || payload.years_of_experience === undefined) {
      delete payload.years_of_experience
    } else {
      // Ensure it's a valid number
      const numValue = Number(payload.years_of_experience)
      if (isNaN(numValue)) {
        delete payload.years_of_experience
      } else {
        payload.years_of_experience = numValue
      }
    }
    
    // Clean up batch field if empty
    if (payload.batch === '' || payload.batch === null) {
      delete payload.batch
    }
    
    // Clean up employment_sector if empty/none
    if (payload.employment_sector === '' || payload.employment_sector === null) {
      delete payload.employment_sector
    }
    
    // Clean up salary_range if empty
    if (payload.salary_range === '' || payload.salary_range === null) {
      delete payload.salary_range
    }

    if (payload.phone_number === '' || payload.phone_number === null) {
      delete payload.phone_number
    }
    
    if (payload.employment_status === '' || payload.employment_status === null) {
      delete payload.employment_status
    }
    
    if (payload.highest_educational_attainment === '' || payload.highest_educational_attainment === null) {
      delete payload.highest_educational_attainment
    }

    delete payload.lcba_employee_id
    delete payload.lcba_verification_status
    
    let res = null
    if (lcbaEmployeeIdFile.value) {
      payload.is_lcba_employee_faculty = true
      const formData = new FormData()
      const appendValue = (key, value) => {
        if (value === undefined || value === null || value === '') return
        if (Array.isArray(value)) {
          value.forEach(item => formData.append(`${key}[]`, item))
          return
        }
        if (typeof value === 'object') {
          Object.entries(value).forEach(([childKey, childValue]) => {
            if (childValue !== undefined && childValue !== null && childValue !== '') {
              formData.append(`${key}[${childKey}]`, childValue)
            }
          })
          return
        }
        if (typeof value === 'boolean') {
          formData.append(key, value ? '1' : '0')
          return
        }
        formData.append(key, value)
      }
      Object.entries(payload).forEach(([key, value]) => appendValue(key, value))
      formData.append('lcba_employee_id_photo', lcbaEmployeeIdFile.value)
      formData.append('_method', 'PUT')
      res = await axios.post('/api/user/profile', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
    } else {
      res = await axios.put('/api/user/profile', payload)
    }
    if (res?.data) {
      // Update local state with server response
      if (res.data.user) {
        authStore.user = res.data.user
        sessionStorage.setItem('user_data', JSON.stringify(res.data.user))
      }
      await authStore.checkAuth()
      initialForm.value = JSON.parse(JSON.stringify(form.value))
      initialPrivacySettings.value = JSON.parse(JSON.stringify(privacySettings.value))
      lcbaEmployeeIdFile.value = null
      if (employeeIdInput.value) {
        employeeIdInput.value.value = ''
      }
      toast.success('Profile updated successfully', 'Success')
    }
  } catch (e) {
    console.error('Save profile failed:', e)
    // Better error handling - show which fields failed
    if (e.response?.data?.errors) {
      const errorFields = Object.keys(e.response.data.errors)
      const errorMessages = errorFields.map(field => {
        const messages = e.response.data.errors[field]
        return `${field}: ${Array.isArray(messages) ? messages.join(', ') : messages}`
      }).join('\n')
      toast.error(`Validation failed:\n${errorMessages}`, 'Validation Error')
    } else {
      const errorMsg = e.response?.data?.message || e.message || 'Failed to save profile. Please try again.'
      toast.error(errorMsg, 'Error')
    }
  } finally {
    saving.value = false
  }
}

const loadEducationHistory = async () => {
  try {
    const response = await axios.get('/api/user/education')
    if (response?.data?.success) {
      educationHistory.value = response.data.data
    }
  } catch (error) {
    console.error('Error loading education history:', error)
  }
}

const getLevelLabel = (level) => {
  const labels = {
    elementary: 'Elementary',
    high_school: 'High School',
    senior_high: 'Senior High School',
    college: 'College',
    masters: "Master's Degree",
    doctorate: 'Doctorate'
  }
  return labels[level] || level
}

const getAttainmentLabel = (value) => {
  const labels = {
    elementary: 'Elementary Graduate',
    high_school: 'High School Graduate',
    senior_high: 'Senior High School Graduate',
    bachelors: "Bachelor's Degree",
    masters: "Master's Degree",
    doctorate: 'Doctorate/PhD'
  }
  return labels[value] || 'N/A'
}

const educationRankMap = {
  elementary: 1,
  high_school: 2,
  senior_high: 3,
  college: 4,
  bachelors: 4,
  masters: 5,
  doctorate: 5
}

const attainmentValueMap = {
  elementary: 'elementary',
  high_school: 'high_school',
  senior_high: 'senior_high',
  college: 'bachelors',
  bachelors: 'bachelors',
  masters: 'masters',
  doctorate: 'doctorate'
}

const attainmentPriority = ['doctorate', 'masters', 'college', 'bachelors', 'senior_high', 'high_school', 'elementary']

const attainmentRankMap = {
  elementary: 1,
  high_school: 2,
  senior_high: 3,
  bachelors: 4,
  masters: 5,
  doctorate: 6
}

const getAttainmentFromProgram = (program) => {
  const value = (program || '').toString().toLowerCase()
  if (!value) return null
  if (value.includes('master')) return 'masters'
  if (value.includes('doctor')) return 'doctorate'
  if (value.includes('bachelor') || value.startsWith('bs ') || value.startsWith('bs') || value.startsWith('ab ')) {
    return 'bachelors'
  }
  if (value.includes('senior high')) return 'senior_high'
  if (value.includes('high school')) return 'high_school'
  if (value.includes('elementary')) return 'elementary'
  return null
}

const getHighestAttainmentFromHistory = (history) => {
  if (!Array.isArray(history) || history.length === 0) return null
  let best = null
  history.forEach((entry) => {
    const level = entry?.level
    if (!level) return
    const rank = educationRankMap[level] || 0
    if (!best || rank > best.rank) {
      best = { level, rank }
      return
    }
    if (rank === best.rank) {
      const bestIndex = attainmentPriority.indexOf(best.level)
      const currentIndex = attainmentPriority.indexOf(level)
      if (currentIndex !== -1 && (bestIndex === -1 || currentIndex < bestIndex)) {
        best = { level, rank }
      }
    }
  })
  if (!best || best.rank === 0) return null
  return attainmentValueMap[best.level] || null
}

const getHighestAttainment = (history, program) => {
  const fromHistory = getHighestAttainmentFromHistory(history)
  const fromProgram = getAttainmentFromProgram(program)
  if (!fromHistory) return fromProgram
  if (!fromProgram) return fromHistory
  const historyRank = attainmentRankMap[fromHistory] || 0
  const programRank = attainmentRankMap[fromProgram] || 0
  return programRank > historyRank ? fromProgram : fromHistory
}

const latestEducationProgram = computed(() => {
  if (!educationHistory.value.length) return form.value.program || ''
  const sorted = [...educationHistory.value].sort((a, b) => {
    const aYear = a.year_graduated ? Number(a.year_graduated) : 0
    const bYear = b.year_graduated ? Number(b.year_graduated) : 0
    if (aYear && bYear && aYear !== bYear) return bYear - aYear
    return (b.id || 0) - (a.id || 0)
  })
  return sorted.find((edu) => edu.program)?.program || form.value.program || ''
})

watch(
  educationHistory,
  (history) => {
    const derivedProgram = latestEducationProgram.value
    form.value.program = derivedProgram
    const derivedAttainment = getHighestAttainment(history, derivedProgram)
    form.value.highest_educational_attainment = derivedAttainment || ''
  },
  { deep: true, immediate: true }
)

const editEducation = (edu) => {
  editingEducation.value = edu
  educationForm.value = {
    level: edu.level,
    school_name: edu.school_name,
    program: edu.program || '',
    year_graduated: edu.year_graduated || '',
    awards: edu.awards || '',
    is_lcba: edu.is_lcba || false
  }
  showEducationModal.value = true
}

const closeEducationModal = () => {
  showEducationModal.value = false
  editingEducation.value = null
  educationForm.value = {
    level: '',
    school_name: '',
    program: '',
    year_graduated: '',
    awards: '',
    is_lcba: false
  }
}

const saveEducation = async () => {
  submittingEducation.value = true
  try {
    const payload = {
      level: educationForm.value.level,
      school_name: educationForm.value.school_name,
      program: educationForm.value.program,
      year_graduated: educationForm.value.year_graduated || null,
      awards: educationForm.value.awards || null,
      is_lcba: isLcbaSchool(educationForm.value.school_name)
    }

    if (editingEducation.value) {
      // Update existing
      const response = await axios.put(`/api/user/education/${editingEducation.value.id}`, payload)
      if (response?.data?.success) {
        await loadEducationHistory()
        closeEducationModal()
        alert('Education updated successfully')
      }
    } else {
      // Create new
      const response = await axios.post('/api/user/education', payload)
      if (response?.data?.success) {
        await loadEducationHistory()
        closeEducationModal()
        alert('Education added successfully')
      }
    }
  } catch (error) {
    console.error('Error saving education:', error)
    alert('Failed to save education: ' + (error.response?.data?.message || error.message))
  } finally {
    submittingEducation.value = false
  }
}

const deleteEducation = async (id) => {
  if (!confirm('Are you sure you want to delete this education entry?')) return

  try {
    const response = await axios.delete(`/api/user/education/${id}`)
    if (response?.data?.success) {
      await loadEducationHistory()
      alert('Education deleted successfully')
    }
  } catch (error) {
    console.error('Error deleting education:', error)
    alert('Failed to delete education')
  }
}

onMounted(() => {
  loadProfile()
  loadFilterOptions()
  loadEducationHistory()
  syncSalaryFromForm()
  window.addEventListener('beforeunload', handleBeforeUnload)
  countriesList.value = countriesList.value
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeunload', handleBeforeUnload)
})

onBeforeRouteLeave(() => {
  if (!hasUnsavedChanges.value) {
    return true
  }
  return confirm('You have unsaved changes. Are you sure you want to leave?')
})
</script>
