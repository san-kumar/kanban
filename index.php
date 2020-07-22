<?php
require_once __DIR__ . '/layout.php';
global $title;
$dir = $_ENV['WORK_DIR'];
$title = ucfirst(basename($dir));
$kanban = "$dir/.kanban";

if ($boards = $_REQUEST['boards'] ?? '')
    exit(file_put_contents($kanban, json_encode(['boards' => json_decode($boards)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));

if (!file_exists($kanban)) file_put_contents($kanban, '{"boards": []}');

$boards = json_decode(file_get_contents($kanban), TRUE);
?>

<div id="app">
    <nav class="navbar navbar-expand-md navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Simple Kanban</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">{{activeBoard.name}}</a>
                        <div class="dropdown-menu dropdown-menu-sm-right" aria-labelledby="navbarDropdown">
                            <a class="dropdown-item disabled">Your kanban boards</a>
                            <a class="dropdown-item" href="#" v-for="(board, i) in boards" :key="board.id" @click.prevent="activeBoardId = board.id" style="min-width: 300px;">
                                <div class="d-flex flex-row align-items-center justify-content-between">
                                    <span><i class="fa fa-fw" :class="{'fa-check': board.id === activeBoard.id}"></i> {{board.name}}</span>
                                    <button class="btn btn-danger btn-sm" type="button" @click.prevent.stop="removeBoard(i)"><i class="fa fa-trash-o"></i></button>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item font-weight-bold text-success" href="#" @click.prevent="createBoard()"><i class="fa fa-plus-circle"></i> Create new kanban board</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container pt-3">
        <div class="row">
            <div v-for="type in types" class="col-sm-4" :style="{opacity: type === 'completed' ? 0.65 : 1}" :key="type">
                <div><input type="search" v-model.trim="search[type]" :placeholder="type + ' &#128269;'" style="font-size: 24px; border: 0;" class="heading"></div>

                <tasks :type="type" :tasks="tasks" @change="rearrange" class="panel" group="tasks" :root="true" :search="search[type]"></tasks>

                <hr/>

                <form @submit.prevent="addTask(type)" class="d-flex flex-row align-items-center mb-3">
                    <input type="text" v-model="task" class="form-control mr-2" :placeholder="`Add ${type} task..`" aria-describedby="new task">
                    <button class="btn btn-primary" type="submit">Add</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    let guid = (type) => type + Math.floor(Math.random() * 99999999999);
    let newTask = (text, type) => ({text, priority: "medium", creation: +Math.floor(+(new Date()) / 1000), id: guid('task'), tasks: [], type});

    Vue.component('tasks', {
        name: 'tasks',
        template: `
        <template>
            <draggable :list="tasks" :group="{name: 'tasks', pull:move, put:move}" @change="e => root ? $emit('change', {e, type}) : ''" class="bg-light p-1">
                <div v-for="(task, index) in tasks" :key="task.id" v-show="visible[task.id]" class="position-relative task" :title="timeSince(task)">
                    <div class="d-flex flex-row align-items-center list-item">
                        <a href="#" class="mr-2 text-muted" :style="{opacity: task.tasks.length ? 1: 0}" @click="$set(task, 'collapsed', !task.collapsed)">{{task.collapsed ? '&#9657;' : '&#9663;'}}</a>
                        <input type="checkbox" class="mr-2" v-model="task.type" :true-value="type === 'todo' ? 'doing' : 'done'" :false-value="type === 'done' ? 'todo' : type"/>
                        <div class="flex-grow-1">
                            <div v-if="task.edit"><input type="text" v-model.lazy.trim="task.text" class="border-0  form-control form-control-sm" @keyup="e => keypress(task, e)" @blur="save(task, index)" :id="task.id+type"></div>
                            <div v-else><a href="#" @click.prevent="edit(task)">{{task.text}}</a></div>
                        </div>
                        <div class="actions pr-1">
                            <a href="#" @click.prevent="duplicate(tasks, task)" title="duplicate">&#8916;</a>
                            <a href="#" @click.prevent="add(task.tasks)" title="create child">+</a>
                            <a href="#" @click.prevent="tasks.splice(index, 1)" title="remove">&times;</a></div>
                        </div>
                    <div v-if="task.tasks && task.tasks instanceof Array" class="pl-3"><tasks :tasks="task.tasks" v-show="!task.collapsed" :type="type"></tasks></div>
                </div>
            </draggable>
        </template>
        `,
        props: ['tasks', 'type', 'search', 'root'],
        methods: {
            keypress(task, e) {
                if (/^(Escape|Enter)$/.test(e.key))
                    this.$delete(task, 'edit');
            },
            add(tasks) {
                let task = newTask('new task', this.type);
                tasks.push(task);
                this.edit(task);
            },
            duplicate(tasks, task) {
                let copy = JSON.parse(JSON.stringify(task, (key, value) => key === 'id' ? guid('task') : value));
                tasks.push(copy);
            },
            edit(task) {
                this.$set(task, 'edit', true);
                setTimeout(() => document.getElementById(task.id + this.type).focus(), 250);
            },
            save(task, i) {
                if (!task.text) this.tasks.splice(i, 1)
                else this.$delete(task, 'edit');
            },
            move(from, to) {
                return !!this.root || (from.el === to.el || from.el.contains(to.el) || to.el.contains(from.el));
            },
            timeSince(task) {
                return timeago.format(task.creation * 1000);
            }
        },
        computed: {
            visible() {
                let vis = {};
                for (let task of this.tasks)
                    vis[task.id] = !this.root || (task.type === this.type) && (!this.search || JSON.stringify(task).indexOf(this.search) !== -1);

                return vis;
            }
        }
    });

    new Vue({
        el: '#app',
        data() {
            return {search: {}, task: '', boards: <?=json_encode($boards['boards'] ?: [])?>, activeBoardId: ''}
        },
        methods: {
            addTask(type) {
                if (this.task !== '')
                    this.tasks.push(newTask(this.task, type));

                this.task = '';
            },
            rearrange(ev) {
                if (ev.e.added && ev.type)
                    this.$set(ev.e.added.element, 'type', ev.type);
            },
            createBoard() {
                let name = prompt('New board name', 'My board');
                if (name) this.addBoard(name);
            },
            addBoard(name) {
                if (!(this.boards instanceof Array)) this.boards = [];
                this.boards.push({id: guid('board'), name, tasks: []});
            },
            removeBoard(index) {
                if (confirm('Are you sure you want to permanently delete this board?'))
                    this.boards.splice(index, 1);
            }
        },
        watch: {
            tasks: {
                deep: true,
                immediate: true,
                handler(tasks) {
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => {
                        let seen = {}, filter = items => {
                            for (let i = items.length - 1; i >= 0; i--) {
                                let item = items[i], id = item.id;
                                if (seen[id]) items.splice(i, 1); else seen[id] = true;
                                if (item.tasks instanceof Array) filter(item.tasks);
                            }
                        };

                        filter(tasks);
                        fetch('', {method: "POST", body: new URLSearchParams("boards=" + JSON.stringify(this.boards))})
                    }, 250);
                },
            }
        },
        computed: {
            activeBoard() {
                if (!this.boards || !this.boards.length) this.addBoard('Main');
                if (!this.activeBoardId) this.activeBoardId = this.boards[0].id;

                for (let board of this.boards) {
                    if (board.id === this.activeBoardId)
                        return board;
                }

                this.activeBoardId = this.boards[0].id;
                return this.boards[0];
            },
            tasks: {
                get() {
                    return this.activeBoard.tasks;
                },
                set(v) {
                    console.log("set tasks: ", v);
                }
            },
            types() {
                return ['todo', 'doing', 'done'];
            },
        }
    });
</script>
