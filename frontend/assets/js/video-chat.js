class VideoChatManager {
    constructor() {
        // DOM elements
        this.localVideo = document.getElementById('local-video');
        this.remoteVideo = document.getElementById('remote-video');
        this.remoteName = document.getElementById('remote-name');
        this.toggleAudioBtn = document.getElementById('toggle-audio');
        this.toggleVideoBtn = document.getElementById('toggle-video');
        this.endCallBtn = document.getElementById('end-call');
        this.matchingOverlay = document.getElementById('matching-overlay');
        this.matchingStatus = document.getElementById('matching-status');
        this.matchingMessage = document.getElementById('matching-message');
        this.cancelMatchBtn = document.getElementById('cancel-match-btn');
        this.videoChatContainer = document.getElementById('video-chat-container');
        this.audioLevel = document.querySelector('.audio-level');

        // WebRTC and state variables
        this.localStream = null;
        this.peerConnection = null;
        this.websocket = null;
        this.matchId = null;
        this.remoteUserId = null;
        this.currentUserId = null;
        this.isInitiator = false;
        this.audioContext = null;
        this.analyser = null;
        this.animationFrame = null;

        // Setup cancel button
        this.cancelMatchBtn.addEventListener('click', () => this.cancelMatching());

        this.init();
    }

    async init() {
        try {
            // Update UI to show we're starting the process
            this.updateMatchingStatus('Checking your camera and microphone...', 'Please allow access when prompted');

            // Get current user first
            const meResponse = await fetch('/api/auth/me');
            const meData = await meResponse.json();

            if (!meData.success) {
                alert('Authentication required');
                router.navigate('login');
                return;
            }

            this.currentUserId = meData.user.id;

            // Get user media
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: true
            });
            this.localVideo.srcObject = this.localStream;

            // Setup audio visualization
            this.setupAudioVisualization();

            // Setup event listeners
            this.setupEventListeners();

            // Update UI to show we're looking for a match
            this.updateMatchingStatus('Looking for your perfect match...', 'This may take a few moments');

            // Set status to available when starting video chat
            await this.setVideoStatus('available');

            // Find a match
            this.findMatch();

        } catch (error) {
            console.error('Error initializing video chat:', error);
            alert('Could not access camera and microphone. Please check your permissions.');
            router.navigate('matches');
        }
    }

    setupAudioVisualization() {
        if (!this.localStream) return;

        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            this.analyser = this.audioContext.createAnalyser();
            const source = this.audioContext.createMediaStreamSource(this.localStream);
            source.connect(this.analyser);
            this.analyser.fftSize = 256;

            if (!this.audioLevel) return;

            const dataArray = new Uint8Array(this.analyser.frequencyBinCount);

            const updateMeter = () => {
                this.analyser.getByteFrequencyData(dataArray);
                let sum = 0;
                for (let i = 0; i < dataArray.length; i++) {
                    sum += dataArray[i];
                }
                const average = sum / dataArray.length;
                const volume = Math.min(100, average * 2);
                this.audioLevel.style.height = volume + '%';

                this.animationFrame = requestAnimationFrame(updateMeter);
            };

            updateMeter();
        } catch (e) {
            console.error('Audio visualization setup failed:', e);
        }
    }

    updateMatchingStatus(status, message) {
        if (this.matchingStatus) this.matchingStatus.textContent = status;
        if (this.matchingMessage) this.matchingMessage.textContent = message;
    }

    async findMatch() {
        try {
            // Get match info from server
            const matchResponse = await fetch('/api/match/video');
            const matchData = await matchResponse.json();

            if (!matchData.success) {
                this.updateMatchingStatus('No matches available', 'Please try again later');
                setTimeout(() => {
                    router.navigate('matches');
                }, 3000);
                return;
            }

            this.matchId = matchData.match_id;
            this.remoteUserId = matchData.user_id;

            // Update UI to show we found a match
            this.updateMatchingStatus('Match found!', `Connecting with ${matchData.first_name}...`);
            if (this.remoteName) this.remoteName.textContent = matchData.first_name;

            // Connect to WebSocket server
            this.connectSignaling();

        } catch (error) {
            console.error('Error finding match:', error);
            this.updateMatchingStatus('Connection error', 'Please try again');
            setTimeout(() => {
                router.navigate('matches');
            }, 3000);
        }
    }

    async cancelMatching() {
        await this.setVideoStatus('offline');
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
        }
        if (this.audioContext) {
            this.audioContext.close();
        }
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
        }
        router.navigate('home');
    }

    async setVideoStatus(status) {
        try {
            const response = await fetch('/api/user/video-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ status })
            });
            const data = await response.json();
            if (!data.success) {
                console.error('Failed to update video status:', data.message);
            }
            return data.success;
        } catch (error) {
            console.error('Error updating video status:', error);
            return false;
        }
    }

    connectSignaling() {
        this.websocket = new WebSocket(`ws://${window.location.hostname}:8080`);

        this.websocket.onopen = () => {
            console.log('Connected to signaling server');
            // Register with the signaling server once connected
            this.sendSignalingMessage('register', {
                userId: this.currentUserId,
                matchId: this.matchId
            });

            // Initialize WebRTC after registering
            this.setupPeerConnection();
        };

        this.websocket.onmessage = (event) => {
            const message = JSON.parse(event.data);
            console.log('Received message:', message.type);
            this.handleSignalingMessage(message);
        };

        this.websocket.onerror = (error) => {
            console.error('WebSocket error:', error);
            this.updateMatchingStatus('Connection error', 'Failed to connect to video server');
        };

        this.websocket.onclose = () => {
            console.log('Disconnected from signaling server');
        };
    }

    sendSignalingMessage(type, data = {}) {
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            const message = {
                type,
                from: this.currentUserId,
                to: this.remoteUserId,
                matchId: this.matchId,
                data
            };
            console.log('Sending message:', type);
            this.websocket.send(JSON.stringify(message));
        } else {
            console.warn('WebSocket not open, cannot send message');
        }
    }

    handleSignalingMessage(message) {
        if (!message || !message.type) return;

        switch (message.type) {
            case 'user-connected':
                console.log('Remote user connected, creating offer');
                // Hide the matching overlay and show the video chat
                if (this.matchingOverlay) this.matchingOverlay.classList.add('hidden');
                if (this.videoChatContainer) this.videoChatContainer.classList.remove('hidden');

                this.isInitiator = true;
                this.createOffer();
                break;

            case 'offer':
                console.log('Received offer, creating answer');
                // Also show the video chat interface when receiving an offer
                if (this.matchingOverlay) this.matchingOverlay.classList.add('hidden');
                if (this.videoChatContainer) this.videoChatContainer.classList.remove('hidden');
                this.handleOffer(message.data);
                break;

            case 'answer':
                console.log('Received answer');
                this.handleAnswer(message.data);
                break;

            case 'ice-candidate':
                console.log('Received ICE candidate');
                this.handleIceCandidate(message.data);
                break;

            case 'user-disconnected':
                console.log('Remote user disconnected');
                this.handleRemoteDisconnect();
                break;

            default:
                console.warn('Unknown message type:', message.type);
        }
    }

    setupPeerConnection() {
        // Create RTCPeerConnection with STUN servers
        this.peerConnection = new RTCPeerConnection({
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        });

        // Add local stream tracks to peer connection
        this.localStream.getTracks().forEach(track => {
            this.peerConnection.addTrack(track, this.localStream);
        });

        // Handle ICE candidates
        this.peerConnection.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignalingMessage('ice-candidate', event.candidate);
            }
        };

        // Handle connection state changes
        this.peerConnection.oniceconnectionstatechange = () => {
            console.log('ICE connection state:', this.peerConnection.iceConnectionState);
            if (this.peerConnection.iceConnectionState === 'disconnected' ||
                this.peerConnection.iceConnectionState === 'failed') {
                console.log('ICE connection failed or disconnected');
            }
        };

        // Handle remote stream
        this.peerConnection.ontrack = (event) => {
            console.log('Remote track received');
            if (event.streams && event.streams[0]) {
                this.remoteVideo.srcObject = event.streams[0];
            }
        };
    }

    async createOffer() {
        try {
            const offer = await this.peerConnection.createOffer();
            await this.peerConnection.setLocalDescription(offer);
            console.log('Created offer');
            this.sendSignalingMessage('offer', offer);
        } catch (error) {
            console.error('Error creating offer:', error);
        }
    }

    async handleOffer(offer) {
        try {
            console.log('Setting remote description from offer');
            await this.peerConnection.setRemoteDescription(new RTCSessionDescription(offer));

            console.log('Creating answer');
            const answer = await this.peerConnection.createAnswer();

            console.log('Setting local description from answer');
            await this.peerConnection.setLocalDescription(answer);

            console.log('Sending answer');
            this.sendSignalingMessage('answer', answer);
        } catch (error) {
            console.error('Error handling offer:', error);
        }
    }

    async handleAnswer(answer) {
        try {
            console.log('Setting remote description from answer');
            await this.peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
        } catch (error) {
            console.error('Error handling answer:', error);
        }
    }

    async handleIceCandidate(candidate) {
        try {
            if (this.peerConnection.remoteDescription) {
                console.log('Adding ICE candidate');
                await this.peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
            } else {
                console.warn('Received ICE candidate before remote description');
            }
        } catch (error) {
            console.error('Error handling ICE candidate:', error);
        }
    }

    handleRemoteDisconnect() {
        alert('The other user disconnected.');
        this.endCall();
    }

    setupEventListeners() {
        this.toggleAudioBtn.addEventListener('click', () => {
            const audioTrack = this.localStream.getAudioTracks()[0];
            if (audioTrack) {
                audioTrack.enabled = !audioTrack.enabled;
                this.toggleAudioBtn.innerHTML = audioTrack.enabled ?
                    '<i class="fa fa-microphone"></i>' :
                    '<i class="fa fa-microphone-slash"></i>';
            }
        });

        this.toggleVideoBtn.addEventListener('click', () => {
            const videoTrack = this.localStream.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.enabled = !videoTrack.enabled;
                this.toggleVideoBtn.innerHTML = videoTrack.enabled ?
                    '<i class="fa fa-video"></i>' :
                    '<i class="fa fa-video-slash"></i>';
            }
        });

        this.endCallBtn.addEventListener('click', () => {
            this.endCall();
        });

        // Handle page navigation/close
        window.addEventListener('beforeunload', () => {
            this.endCall();
        });
    }

    async endCall() {
        // Update status to offline
        await this.setVideoStatus('offline');

        // Stop all tracks
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
        }

        // Close peer connection
        if (this.peerConnection) {
            this.peerConnection.close();
            this.peerConnection = null;
        }

        // Close WebSocket
        if (this.websocket && this.websocket.readyState === WebSocket.OPEN) {
            this.sendSignalingMessage('disconnect');
            this.websocket.close();
        }

        // Clean up audio visualization
        if (this.audioContext) {
            this.audioContext.close();
        }
        if (this.animationFrame) {
            cancelAnimationFrame(this.animationFrame);
        }

        // Create a chat with this match
        if (this.matchId) {
            try {
                await fetch(`/api/chats/create/${this.matchId}`, {
                    method: 'POST'
                });
            } catch (error) {
                console.error('Error creating chat after video call:', error);
            }
        }

        // Navigate back to matches
        router.navigate('matches');
    }
}

window.VideoChatManager = VideoChatManager;