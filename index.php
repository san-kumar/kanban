<?php
require_once __DIR__ . '/layout.php';
global $title;
$dir = $_ENV['WORK_DIR'];
$title = ucfirst(basename($dir)) ?: 'Main';
$kanban = "$dir/.kanban";

if ($data = $_REQUEST['data'] ?? '') {
    copy($kanban, "/tmp/.kanban-"  .dirname($dir). '-'. time() . ".bak");
    exit(file_put_contents($kanban, $data));//json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
}

$default = '{}';
$data = file_exists($kanban) ? file_get_contents($kanban) ?: $default : $default;
?>

<div id="app" v-if="activeBoard">
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
                                    <div class="btn-group">
                                        <button class="btn btn-info btn-sm" type="button" @click.prevent.stop="renameBoard(i)" title="Rename"><i class="fa fa-edit"></i></button>
                                        <button class="btn btn-danger btn-sm" type="button" @click.prevent.stop="removeBoard(i)" title="Remove"><i class="fa fa-trash-o"></i></button>
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item font-weight-bold text-success" href="#" @click.prevent="createBoard()"><i class="fa fa-plus-circle"></i> Create new kanban board</a>
                        </div>
                    </li>
                    <li class="nav-item" title="Settings"><a class="nav-link" href="#" @click.prevent="showSettings()"><i class="fa fa-cog"></i></a></li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container pt-3">
        <div class="row">
            <div v-for="type in types" :class="`col-sm-${12/types.length}`" :style="{opacity: type === 'completed' ? 0.65 : 1}" :key="type">
                <div><input type="search" v-model.trim="search[type]" :placeholder="type + ' &#128269;'" style="font-size: 24px; border: 0;" class="heading"></div>

                <div @click="sel=null">
                    <tasks :type="type" :tasks="tasks" @change="rearrange" class="panel" :class="[type, settings.oneDoingItem ? 'one-item-only' : '']" group="tasks" :root="true" :search="search[type]"></tasks>
                </div>

                <hr/>

                <form @submit.prevent="addTask(type)" class="d-flex flex-row align-items-center mb-3">
                    <a href="#" v-if="sel && sel.type === type" @click.prevent="sel = getParent(sel)"><i class="fa fa-level-up fa-lg mr-1"></i></a>
                    <input type="text" v-model="task[type]" class="form-control mr-2" :placeholder="addPlaceholder[type]" aria-describedby="new task">
                    <button class="btn btn-primary" type="submit">Add</button>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" ref="settingsModal">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <h3>Kanban settings</h3>

                    <label class="checkbox-inline">
                        <input type="checkbox" v-model="settings.oneDoingItem"> Only one <i>Doing</i> item
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" data-dismiss="modal" class="btn btn-primary">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let guid = (type) => type + Math.floor(Math.random() * 99999999999);
    let newTask = (text, type) => ({text, priority: "medium", creation: +Math.floor(+(new Date()) / 1000), id: guid('task'), tasks: [], type});
    let types = ['todo', 'doing', 'done']; //add remove default boards here

    Vue.component('tasks', {
        name: 'tasks',
        template: `
        <template>
            <draggable :list="tasks" :group="{name: 'tasks', pull:move, put:move}" @change="e => root ? $emit('change', {e, type}) : ''" class="bg-light p-1">
                <div v-for="(task, index) in tasks" :key="task.id" v-show="visible[task.id]" class="position-relative task" :title="timeSince(task)">
                    <div class="d-flex flex-row align-items-center list-item">
                        <a href="#" class="mr-2 text-muted" :style="{opacity: task.tasks.length ? 1: 0}" @click="$set(task, 'collapsed', !task.collapsed)">{{task.collapsed ? '&#9657;' : '&#9663;'}}</a>
                        <input type="checkbox" class="mr-2" v-model="task.type" :true-value="type !== lastType ? nextType(type) : lastType" :false-value="type === lastType ? firstType : type" @input="$set(task, 'updated', +new Date())" />
                        <div class="flex-grow-1" @click.stop="sel = !task.edit && sel !== task ? task : null" :class="sel === task ? 'bg-selected' : ''">
                            <div v-if="task.edit"><input type="text" v-model.lazy.trim="task.text" class="border-0  form-control form-control-sm" @keyup="e => keypress(task, e)" @blur="save(task, index)" :id="task.id+type"></div>
                            <div v-else><a href="#" @click.prevent.stop="edit(task)">{{task.text}}</a></div>
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
            },
            nextType(type) {
                return types[types.indexOf(type) + 1];
            }
        },
        computed: {
            firstType() {
                return types[0];
            },
            lastType() {
                return types[types.length - 1];
            },
            visible() {
                let vis = {};
                for (let task of this.tasks)
                    vis[task.id] = !this.root || (task.type === this.type) && (!this.search || JSON.stringify(task).indexOf(this.search) !== -1);

                return vis;
            },
            sel: {
                get() {
                    return this.$root.sel;
                },
                set(v) {
                    this.$set(this.$root, 'sel', v);
                }
            }
        }
    });

    new Vue({
        el: '#app',
        data() {
            return {search: {}, task: {}, data: <?=$data?>, sel: null};
        },
        methods: {
            addTask(type) {
                if (this.task[type] !== '') {
                    let parent = this.sel && this.sel.type === type ? this.sel.tasks : this.tasks;
                    parent.push(newTask(this.task[type], type));
                }

                this.task[type] = '';
            },
            getParent(sel) {
                let last = null, parent = null, ignore = JSON.stringify(this.data, (key, value) => {
                    if (value.tasks instanceof Array && value.tasks.indexOf(sel) !== -1) parent = value;
                    return value;
                });

                return parent;
            },
            rearrange(ev) {
                if (ev.e.added && ev.type)
                    this.$set(ev.e.added.element, 'type', ev.type);
            },
            createBoard() {
                let name = prompt('New board name', 'My board #' + this.boards.length);
                if (name) this.addBoard(name, true);
            },
            addBoard(name, switchTo = false) {
                let id = guid('board');
                this.data.boards.push({id, name, tasks: []});
                if (switchTo === true) this.activeBoardId = id;
            },
            renameBoard(index) {
                let name = prompt('Rename board to:', this.boards[index].name);
                if (name) this.boards[index].name = name;
            },
            removeBoard(index) {
                if (confirm('Are you sure you want to permanently delete this board?'))
                    this.boards.splice(index, 1);
            },
            showSettings() {
                $(this.$refs.settingsModal).modal('show');
            }
        },
        watch: {
            data: {
                deep: true,
                immediate: true,
                handler() {
                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => {
                        let seen = {}, doing = '', filter = items => { //TODO this hack must be removed
                            for (let i = items.length - 1; i >= 0; i--) {
                                let item = items[i], id = item.id;
                                if (seen[id]) items[i].id = guid('task'); else seen[id] = true;
                                if (item.tasks instanceof Array) filter(item.tasks);
                            }
                        };

                        for (let item of this.activeBoard.tasks)
                            if (item.type === 'doing' && this.settings.oneDoingItem)
                                if (!doing) doing = item.id; else this.$set(item, 'type', 'todo');

                        filter(this.activeBoard.tasks);
                        fetch('', {method: "POST", body: new URLSearchParams("data=" + JSON.stringify(this.data))})
                    }, 250);
                },
            }
        },
        computed: {
            addPlaceholder() {
                let placeholders = {};

                for (let type of this.types)
                    placeholders[type] = this.sel && this.sel.type === type ? `Add child to ${this.sel.text.substr(0, 10)}` : `Add ${type} task..`;

                return placeholders;
            },
            boards() {
                if (!this.data || !(this.data.boards instanceof Array)) this.data.boards = [];
                if (!this.data.boards.length) this.addBoard('Main board', true);

                return this.data.boards;
            },
            activeBoardId: {
                get() {
                    return this.data.activeBoardId || this.boards[0].id;
                },
                set(v) {
                    this.$set(this.data, 'activeBoardId', v);
                }
            },
            activeBoard() {
                for (let board of this.boards) {
                    if (board.id === this.activeBoardId)
                        return board;
                }

                this.activeBoardId = this.boards[0].id;
                return this.boards[0];
            },
            tasks() {
                return this.activeBoard.tasks;
            },
            types() {
                return types;
            },
            settings: {
                get() {
                    if (!this.data) return {};
                    if (this.data && !this.data.settings) this.$set(this.data, 'settings', {});
                    return this.data.settings;
                },
                set(v) {
                    this.$set(this.data, 'settings', v);
                }
            }
        }
    });
</script>
